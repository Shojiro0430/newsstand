<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');
require_once('../../incl/battlenet.incl.php');

mb_internal_encoding("UTF-8");

if (!isset($_GET['realm']) || !isset($_GET['seller'])) {
    json_return(array());
}

$realm = intval($_GET['realm'], 10);
$seller = mb_convert_case(mb_substr($_GET['seller'], 0, 12), MB_CASE_LOWER);

if (!$seller || !$realm || (!($house = GetHouse($realm))) || (GetRegion($house) == 'EU')) {
    json_return(array());
}

HouseETag($house);
ConcurrentRequestThrottle();
BotCheck();

$sellerRow = SellerStats($house, $realm, $seller);
if (!$sellerRow) {
    json_return(array());
}

$json = array(
    'stats'       => $sellerRow,
    'history'     => SellerHistory($house, $sellerRow['id']),
    'byClass'     => SellerByClass($house, $sellerRow['id']),
    'auctions'    => SellerAuctions($house, $sellerRow['id']),
    'petAuctions' => SellerPetAuctions($house, $sellerRow['id']),
);

unset($json['stats']['id']);

json_return($json);

function SellerStats($house, $realm, $seller)
{
    $seller = mb_ereg_replace(' ', '', $seller);
    $seller = mb_strtoupper(mb_substr($seller, 0, 1)) . mb_strtolower(mb_substr($seller, 1));

    $key = 'seller_stats_r2_' . $realm . '_' . $seller;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    $db = DBConnect();

    $sql = <<<'EOF'
    SELECT id, realm, name, unix_timestamp(firstseen) firstseen, unix_timestamp(lastseen) lastseen
    FROM tblSeller
    WHERE realm = ?
    AND name = ?
    AND lastseen is not null
EOF;
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $realm, $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    if (count($tr)) {
        $tr = $tr[0];
    }
    $stmt->close();

    if ($tr) {
        $tr['thumbnail'] = SellerThumbnail($realm, $seller);
        $tr = $tr + SellerRank($house, $tr['id']);
    }

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function SellerRank($house, $seller) {
    // uncached: only called (and cached) from SellerStats

    $db = DBConnect();

    $sql = <<<'EOF'
select ifnull(sum(if(a.buy = 0, a.bid, a.buy)),0) uservalue,
ifnull(sum(ifnull(s.price, ps.price) * a.quantity),0) marketvalue,
ifnull(sum(ifnull(g.median, pg.median) * a.quantity),0) regionmedian,
count(*) auctions
from tblAuction a
join tblRealm r on r.house = a.house and r.canonical is not null
left join tblAuctionExtra ae on ae.id = a.id and ae.house = a.house
left join tblItemSummary s on a.item = s.item and s.level = ifnull(ae.level, 0) and s.house = a.house and a.item != %1$d
left join tblItemGlobal g on a.item = g.item and g.level = ifnull(ae.level, 0) and g.region = r.region and a.item != %1$d
left join tblAuctionPet ap on ap.id = a.id and ap.house = a.house
left join tblPetSummary ps on ap.species = ps.species and ps.house = a.house and a.item = %1$d
left join tblPetGlobal pg on ap.species = pg.species and pg.region = r.region and a.item = %1$d
where a.seller = ?
EOF;

    $stmt = $db->prepare(sprintf($sql, BATTLE_PET_CAGE_ITEM));
    $stmt->bind_param('i', $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    if (count($tr)) {
        $tr = $tr[0];

        foreach (['auctions', 'uservalue', 'marketvalue', 'regionmedian'] as $t) {
            if ($tr[$t]) {
                $tr[$t.'rank'] = SellerRankByType($t, $house, $tr[$t]);
            }
        }
    }

    return $tr;
}

function SellerRankByType($type, $house, $amount) {
    $sqls = [];

    $sqls['auctions'] = <<<'EOF'
    select count(*)
    from tblAuction a
    where a.house = ?
    group by a.seller
    order by 1 desc
EOF;

    $sqls['uservalue'] = <<<'EOF'
    select sum(if(a.buy = 0, a.bid, a.buy))
    from tblAuction a
    where a.house = ?
    group by a.seller
    order by 1 desc
EOF;

    $sqls['marketvalue'] = <<<'EOF'
    select sum(ifnull(s.price, ps.price) * a.quantity)
    from tblAuction a
    left join tblAuctionExtra ae on ae.id = a.id and ae.house = a.house
    left join tblItemSummary s on a.item = s.item and s.level = ifnull(ae.level, 0) and s.house = a.house and a.item != %1$d
    left join tblAuctionPet ap on ap.id = a.id and ap.house = a.house
    left join tblPetSummary ps on ap.species = ps.species and ps.house = a.house and a.item = %1$d
    where a.house = ?
    group by a.seller
    order by 1 desc
EOF;

    $sqls['regionmedian'] = <<<'EOF'
    select sum(ifnull(g.median, pg.median) * a.quantity)
    from tblAuction a
    join tblRealm r on r.house = a.house and r.canonical is not null
    left join tblAuctionExtra ae on ae.id = a.id and ae.house = a.house
    left join tblItemGlobal g on a.item = g.item and g.level = ifnull(ae.level, 0) and g.region = r.region and a.item != %1$d
    left join tblAuctionPet ap on ap.id = a.id and ap.house = a.house
    left join tblPetGlobal pg on ap.species = pg.species and pg.region = r.region and a.item = %1$d
    where a.house = ?
    group by a.seller
    order by 1 desc
EOF;

    if (!isset($sqls[$type])) {
        return 0;
    }

    $key = 'seller_rank_' . $type;

    if (($ranks = MCGetHouse($house, $key)) === false) {
        $sql = $sqls[$type];

        $db = DBConnect();

        $stmt = $db->prepare(sprintf($sql, BATTLE_PET_CAGE_ITEM));
        $stmt->bind_param('i', $house);
        $stmt->execute();
        $result = $stmt->get_result();
        $ranks  = DBMapArray($result, null);
        $stmt->close();

        MCSetHouse($house, $key, $ranks);
    }

    // search for amount within ranks

    $lo = 0;
    $hi = count($ranks) - 1;

    while ($lo <= $hi) {
        $mid = (int)(($hi - $lo) / 2) + $lo;

        if ($amount > $ranks[$mid]) {
            $hi = $mid - 1;
        } elseif ($amount < $ranks[$mid]) {
            $lo = $mid + 1;
        } else {
            return $mid + 1;
        }
    }

    return $lo + 1;
}

function SellerHistory($house, $seller)
{
    $key = 'seller_history2_' . $seller;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    $db = DBConnect();

    $historyDays = HISTORY_DAYS;

    $sql = <<<EOF
select unix_timestamp(s.updated) snapshot, ifnull(case hour(s.updated)
    when  0 then h.total00 when  1 then h.total01 when  2 then h.total02 when  3 then h.total03
    when  4 then h.total04 when  5 then h.total05 when  6 then h.total06 when  7 then h.total07
    when  8 then h.total08 when  9 then h.total09 when 10 then h.total10 when 11 then h.total11
    when 12 then h.total12 when 13 then h.total13 when 14 then h.total14 when 15 then h.total15
    when 16 then h.total16 when 17 then h.total17 when 18 then h.total18 when 19 then h.total19
    when 20 then h.total20 when 21 then h.total21 when 22 then h.total22 when 23 then h.total23
    else null end, 0) `total`, ifnull(case hour(s.updated)
    when  0 then h.new00 when  1 then h.new01 when  2 then h.new02 when  3 then h.new03
    when  4 then h.new04 when  5 then h.new05 when  6 then h.new06 when  7 then h.new07
    when  8 then h.new08 when  9 then h.new09 when 10 then h.new10 when 11 then h.new11
    when 12 then h.new12 when 13 then h.new13 when 14 then h.new14 when 15 then h.new15
    when 16 then h.new16 when 17 then h.new17 when 18 then h.new18 when 19 then h.new19
    when 20 then h.new20 when 21 then h.new21 when 22 then h.new22 when 23 then h.new23
    else null end,0) as `new`
from tblSnapshot s
left join tblSellerHistoryHourly h on date(s.updated) = h.`when` and h.seller=?
where s.house = ? and s.updated >= timestampadd(day,-$historyDays,now()) and s.flags & 1 = 0
order by s.updated asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $seller, $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    while (count($tr) > 0 && is_null($tr[0]['total'])) {
        array_shift($tr);
    }

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function SellerByClass($house, $seller)
{
    $key = 'seller_byclass_' . $seller;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    $db = DBConnect();

    $historyDays = HISTORY_DAYS;

    $sql = <<<EOF
select i.class, i.subclass, sum(sih.auctions) aucs
from tblSellerItemHistory sih use index (seller)
join tblDBCItem i on i.id = sih.item
where sih.seller = ?
and sih.snapshot >= timestampadd(day,-$historyDays,now())
group by i.class, i.subclass
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function SellerAuctions($house, $seller)
{
    $cacheKey = 'seller_auctions_l2_' . $seller;
    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        PopulateLocaleCols($tr, [
                ['func' => 'GetItemNames',          'key' => 'item',    'name' => 'name'],
                ['func' => 'GetItemBonusNames',     'key' => 'bonuses', 'name' => 'bonusname'],
                ['func' => 'GetRandEnchantNames',   'key' => 'rand',    'name' => 'randname'],
            ], true);
        return $tr;
    }

    $db = DBConnect();

    $sql = <<<EOF
select item, level, baselevel, quality, class, subclass, icon, stacksize, quantity, bid, buy, `rand`, seed, lootedlevel, bonuses,
(SELECT ifnull(sum(quantity),0) from tblAuction a2 left join tblAuctionExtra ae2 on a2.house=ae2.house and a2.id=ae2.id where a2.house=results.house and a2.item=results.item and ifnull(ae2.level,0) = ifnull(results.level,0) and
((results.buy > 0 and a2.buy > 0 and (a2.buy / a2.quantity < results.buy / results.quantity)) or (results.buy = 0 and (a2.bid / a2.quantity < results.bid / results.quantity)))) cheaper
from (
    SELECT a.item, ae.level, i.quality, i.class, i.subclass, i.icon, i.stacksize, a.quantity, a.bid, a.buy,
    ifnull(ae.`rand`, 0) `rand`, ifnull(ae.seed,0) seed, ae.lootedlevel,
    concat_ws(':',ae.bonus1,ae.bonus2,ae.bonus3,ae.bonus4,ae.bonus5,ae.bonus6) bonuses,
    a.house, a.seller, i.level baselevel
    FROM `tblAuction` a
    left join tblDBCItem i on a.item=i.id
    left join tblAuctionExtra ae on ae.house=a.house and ae.id=a.id
    WHERE a.seller = ?
    and a.item != %d
    group by a.id
) results
EOF;

    $stmt = $db->prepare(sprintf($sql, BATTLE_PET_CAGE_ITEM));
    $stmt->bind_param('i', $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    PopulateLocaleCols($tr, [
            ['func' => 'GetItemNames',          'key' => 'item',    'name' => 'name'],
            ['func' => 'GetItemBonusNames',     'key' => 'bonuses', 'name' => 'bonusname'],
            ['func' => 'GetRandEnchantNames',   'key' => 'rand',    'name' => 'randname'],
        ], true);

    return $tr;
}

function SellerPetAuctions($house, $seller)
{
    $cacheKey = 'seller_petauctions_' . $seller;

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        PopulateLocaleCols($tr, [['func' => 'GetPetNames', 'key' => 'species', 'name' => 'name']], true);
        return $tr;
    }

    $db = DBConnect();

    $sql = <<<EOF
SELECT ap.species, ap.breed, quantity, bid, buy, ap.level, ap.quality, p.icon, p.type, p.npc,
(SELECT ifnull(sum(quantity),0)
from tblAuctionPet ap2
join tblAuction a2 on a2.house = ap2.house and a2.id = ap2.id
where ap2.house=a.house and ap2.species = ap.species and ap2.level >= ap.level and
((a.buy > 0 and a2.buy > 0 and (a2.buy / a2.quantity < a.buy / a.quantity)) or (a.buy = 0 and (a2.bid / a2.quantity < a.bid / a.quantity)))) cheaper
FROM `tblAuction` a
JOIN `tblAuctionPet` ap on a.house = ap.house and a.id = ap.id
JOIN `tblDBCPet` `p` on `p`.`id` = `ap`.`species`
WHERE a.house = ? and a.seller = ? and a.item = %d
EOF;

    $stmt = $db->prepare(sprintf($sql, BATTLE_PET_CAGE_ITEM));
    $stmt->bind_param('ii', $house, $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    PopulateLocaleCols($tr, [['func' => 'GetPetNames', 'key' => 'species', 'name' => 'name']], true);

    return $tr;
}

function SellerThumbnail($realmId, $seller) {
    $key = 'seller_thumbnail_' . $realmId . '_' . $seller;

    if (($tr = MCGet($key)) !== false) {
        return $tr;
    }

    $realmRec = GetRealmById($realmId);
    $region = strtolower($realmRec['region']);

    $url = GetBattleNetURL($region, sprintf("wow/character/%s/%s", $realmRec['slug'], $seller));
    $json = json_decode(\Newsstand\HTTP::Get($url), true);

    $tr = isset($json['thumbnail']) ? $json['thumbnail'] : '';

    MCSet($key, $tr, 6 * 60 * 60);

    return $tr;
}

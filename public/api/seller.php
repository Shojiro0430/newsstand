<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

mb_internal_encoding("UTF-8");

if (!isset($_GET['realm']) || !isset($_GET['seller'])) {
    json_return(array());
}

$realm = intval($_GET['realm'], 10);
$seller = mb_convert_case(mb_substr($_GET['seller'], 0, 12), MB_CASE_LOWER);

if (!$seller || !$realm || (!($house = GetHouse($realm)))) {
    json_return(array());
}

BotCheck();
HouseETag($house);

$sellerRow = SellerStats($house, $realm, $seller);
if (!$sellerRow) {
    json_return(array());
}

$json = array(
    'stats'       => $sellerRow,
    'history'     => SellerHistory($house, $sellerRow['id']),
    'auctions'    => SellerAuctions($house, $sellerRow['id']),
    'petAuctions' => SellerPetAuctions($house, $sellerRow['id']),
);

json_return($json);

function SellerStats($house, $realm, $seller)
{
    global $db;

    $seller = mb_ereg_replace(' ', '', $seller);
    $seller = mb_strtoupper(mb_substr($seller, 0, 1)) . mb_strtolower(mb_substr($seller, 1));

    $key = 'seller_stats_' . $realm . '_' . $seller;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = 'SELECT * FROM tblSeller s WHERE realm = ? AND name = ?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $realm, $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    if (count($tr)) {
        $tr = $tr[0];
    }
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function SellerHistory($house, $seller)
{
    global $db;

    $key = 'seller_history2_' . $seller;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $historyDays = HISTORY_DAYS;

    $sql = <<<EOF
select unix_timestamp(s.updated) snapshot, ifnull(h.`total`, 0) `total`, ifnull(h.`new`,0) as `new`
from tblSnapshot s
left join tblSellerHistory h on s.updated = h.snapshot and h.seller=?
where s.house = ? and s.updated >= timestampadd(day,-$historyDays,now()) and s.flags & 1 = 0
order by s.updated asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $seller, $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    while (count($tr) > 0 && is_null($tr[0]['total'])) {
        array_shift($tr);
    }

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function SellerAuctions($house, $seller)
{
    global $db;

    if (($tr = MCGetHouse($house, 'seller_auctions_' . $seller)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
SELECT a.item, i.name, i.quality, i.class, i.subclass, i.icon, i.stacksize, a.quantity, a.bid, a.buy, ifnull(ae.`rand`, 0) `rand`, ifnull(ae.seed,0) seed,
(SELECT ifnull(sum(quantity),0) from tblAuction a2 where a2.house=a.house and a2.item=a.item and a2.seller!=a.seller and
((a.buy > 0 and a2.buy > 0 and (a2.buy / a2.quantity < a.buy / a.quantity)) or (a.buy = 0 and (a2.bid / a2.quantity < a.bid / a.quantity)))) cheaper
FROM `tblAuction` a
left join tblDBCItem i on a.item=i.id
left join tblAuctionExtra ae on ae.house=a.house and ae.id=a.id
WHERE a.house = ? and a.seller = ?
and a.item != 82800
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, 'seller_auctions_' . $seller, $tr);

    return $tr;
}

function SellerPetAuctions($house, $seller)
{
    global $db;

    if (($tr = MCGetHouse($house, 'seller_petauctions_' . $seller)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
SELECT ap.species, ap.breed, quantity, bid, buy, ap.level, ap.quality, p.name, p.icon, p.type, p.npc,
(SELECT ifnull(sum(quantity),0)
from tblAuction a2
join tblAuctionPet ap2 on a2.house = ap2.house and a2.id = ap2.id
where a2.house=a.house and a2.item=a.item and ap2.species = ap.species and ap2.level >= ap.level and a2.seller!=a.seller and
((a.buy > 0 and a2.buy > 0 and (a2.buy / a2.quantity < a.buy / a.quantity)) or (a.buy = 0 and (a2.bid / a2.quantity < a.bid / a.quantity)))) cheaper
FROM `tblAuction` a
JOIN `tblAuctionPet` ap on a.house = ap.house and a.id = ap.id
JOIN `tblPet` `p` on `p`.`id` = `ap`.`species`
WHERE a.house = ? and a.seller = ? and a.item = 82800
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, 'seller_petauctions_' . $seller, $tr);

    return $tr;
}


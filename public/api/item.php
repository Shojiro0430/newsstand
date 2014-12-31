<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['item'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);
$item = intval($_GET['item'], 10);

if (!$item) {
    json_return(array());
}

BotCheck();
HouseETag($house);

$json = array(
    'stats'         => ItemStats($house, $item),
    'history'       => ItemHistory($house, $item),
    'daily'         => ItemHistoryDaily($house, $item),
    'monthly'       => ItemHistoryMonthly($house, $item),
    'auctions'      => ItemAuctions($house, $item),
    'globalnow'     => ItemGlobalNow(GetRegion($house), $item),
    'globalmonthly' => ItemGlobalMonthly(GetRegion($house), $item),
);

json_return($json);

function ItemStats($house, $item)
{
    global $db;

    $cacheKey = 'item_statsb3_' . $item;

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
select i.id, i.name, i.icon, i.class as classid, i.subclass, ifnull(max(ib.quality), i.quality) quality, i.level+sum(ifnull(ib.level,0)) level, i.stacksize, i.binds, i.buyfromvendor, i.selltovendor, i.auctionable,
s.price, s.quantity, s.lastseen,
ifnull(s.bonusset,0) bonusset, ifnull(GROUP_CONCAT(bs.`bonus` ORDER BY 1 SEPARATOR ':'), '') bonusurl,
ifnull(group_concat(ib.`tag` order by ib.tagpriority separator ' '), if(ifnull(s.bonusset,0)=0,'',concat('Level ', i.level+sum(ifnull(ib.level,0))))) bonustag
from tblDBCItem i
left join tblItemSummary s on s.house = ? and s.item = i.id
left join tblBonusSet bs on s.bonusset = bs.`set`
left join tblDBCItemBonus ib on bs.bonus = ib.id
where i.id = ?
group by s.bonusset
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('bonusset', null));
    foreach ($tr as &$bonusRow) {
        $bonusRow = array_pop($bonusRow);
    }
    unset($bonusRow);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function ItemHistory($house, $item)
{
    global $db;

    $key = 'item_historyb_' . $item;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $historyDays = HISTORY_DAYS;

    $sql = <<<EOF
select bonusset, snapshot, price, quantity, age
from (select
    if(bonusset = @prevBonusSet, null, @price := null) resetprice,
    if(bonusset = @prevBonusSet, null, @age := null) resetage,
    (@prevBonusSet := bonusset) as bonusset,
    unix_timestamp(updated) snapshot,
    cast(if(quantity is null, @price, @price := price) as decimal(11,0)) `price`,
    ifnull(quantity,0) as quantity,
    if(age is null, @age, @age := age) as age
    from (select @price := null, @age := null, @prevBonusSet := null) priceSetup,
        (select ifnull(ih.bonusset,0) bonusset, s.updated, ih.quantity, ih.price, ih.age
        from tblSnapshot s
        left join tblItemHistory ih on s.updated = ih.snapshot and ih.house=? and ih.item=?
        where s.house = ? and s.updated >= timestampadd(day,-$historyDays,now()) and s.flags & 1 = 0
        group by ih.bonusset
        order by ih.bonusset, s.updated asc
        ) ordered
    ) withoutresets
EOF;

    $stmt = $db->prepare($sql);
    $realHouse = abs($house);
    $stmt->bind_param('iii', $house, $item, $realHouse);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('bonusset', null));
    $stmt->close();

    foreach ($tr as &$bonusSet) {
        while (count($bonusSet) > 0 && is_null($bonusSet[0]['price'])) {
            array_shift($bonusSet);
        }
    }
    unset($bonusSet);

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function ItemHistoryDaily($house, $item)
{
    global $db;

    $cacheKey = 'item_historydaily_' . $house . '_' . $item;

    if (($tr = MCGet($cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
select `when` as `date`,
`pricemin` as `silvermin`, `priceavg` as `silveravg`, `pricemax` as `silvermax`,
`pricestart` as `silverstart`, `priceend` as `silverend`,
`quantitymin`, `quantityavg`, `quantitymax`, round(`presence`/255*100,1) as `presence`
from tblItemHistoryDaily
where house = ? and item = ?
order by `when` asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSet($cacheKey, $tr, 60 * 60 * 8);

    return $tr;
}

function ItemHistoryMonthly($house, $item)
{
    global $db;

    $cacheKey = 'item_historymonthly_' . $house . '_' . $item;

    if (($tr = MCGet($cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
select *
from tblItemHistoryMonthly
where house = ? and item = ?
order by `month` asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $bonusRows = DBMapArray($result, array('bonusset', null));
    $stmt->close();

    $tr = [];
    foreach ($bonusRows as $bonusSet => &$rows) {
        $prevPrice = 0;
        for ($x = 0; $x < count($rows); $x++) {
            $year = 2014 + floor(($rows[$x]['month'] - 1) / 12);
            $monthNum = $rows[$x]['month'] % 12;
            if ($monthNum == 0) {
                $monthNum = 12;
            }
            $month = ($monthNum < 10 ? '0' : '') . $monthNum;
            for ($dayNum = 1; $dayNum <= 31; $dayNum++) {
                $day = ($dayNum < 10 ? '0' : '') . $dayNum;
                if (!is_null($rows[$x]['mktslvr' . $day])) {
                    $tr[$bonusSet][] = array('date'     => "$year-$month-$day",
                                  'silver'   => $rows[$x]['mktslvr' . $day],
                                  'quantity' => $rows[$x]['qty' . $day]
                    );
                    $prevPrice = $rows[$x]['mktslvr' . $day];
                } else {
                    if (!checkdate($monthNum, $dayNum, $year)) {
                        break;
                    }
                    if (strtotime("$year-$month-$day") > time()) {
                        break;
                    }
                    if ($prevPrice) {
                        $tr[$bonusSet][] = array('date' => "$year-$month-$day", 'silver' => $prevPrice, 'quantity' => 0);
                    }
                }
            }
        }
    }
    unset($rows);

    MCSet($cacheKey, $tr, 60 * 60 * 8);

    return $tr;
}

function ItemAuctions($house, $item)
{
    global $db;

    $cacheKey = 'item_auctionsb3_' . $item;

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
SELECT ifnull(ae.bonusset,0) bonusset, a.quantity, a.bid, a.buy, ifnull(ae.`rand`,0) `rand`, ifnull(ae.seed,0) `seed`, s.realm sellerrealm, ifnull(s.name, '???') sellername,
concat_ws(':',ae.bonus1,ae.bonus2,ae.bonus3,ae.bonus4,ae.bonus5,ae.bonus6) bonuses,
ifnull(GROUP_CONCAT(distinct bs.`bonus` ORDER BY 1 SEPARATOR ':'), '') bonusurl,
ifnull(group_concat(distinct ib.name order by ib.namepriority desc separator '|'), '') bonusname,
ifnull(group_concat(distinct ib.`tag` order by ib.tagpriority separator ' '), if(ifnull(bs.set,0)=0,'',concat('Level ', i.level+sum(ifnull(ib.level,0))))) bonustag,
re.name randname
FROM `tblAuction` a
join tblDBCItem i on a.item=i.id
left join tblSeller s on a.seller=s.id
left join tblAuctionExtra ae on ae.house=a.house and ae.id=a.id
left join tblBonusSet bs on ae.bonusset = bs.set
left join tblDBCItemBonus ib on ib.id in (ae.bonus1, ae.bonus2, ae.bonus3, ae.bonus4, ae.bonus5, ae.bonus6)
left join tblDBCRandEnchants re on re.id = ae.rand
WHERE a.house=? and a.item=?
group by a.id
EOF;
    // order by buy/quantity, bid/quantity, quantity, s.name, a.id


    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('bonusset', null));
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function ItemGlobalNow($region, $item)
{
    global $db;

    $key = 'item_globalnow2_' . $region . '_' . $item;
    if (($tr = MCGet($key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
    SELECT i.bonusset, r.house, i.price, i.quantity, unix_timestamp(i.lastseen) as lastseen
FROM `tblItemSummary` i
join tblRealm r on i.house = r.house and r.region = ?
WHERE i.item=?
group by i.bonusset, r.house
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('si', $region, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('bonusset', null));
    $stmt->close();

    MCSet($key, $tr, 60 * 60);

    return $tr;
}


function ItemGlobalMonthly($region, $item)
{
    global $db;

    $key = 'item_globalmonthly2_' . $region . '_' . $item;
    if (($tr = MCGet($key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sqlCols = '';
    for ($x = 1; $x <= 31; $x++) {
        $padded = str_pad($x, 2, '0', STR_PAD_LEFT);
        $sqlCols .= ", round(avg(mktslvr$padded)*100) mkt$padded, ifnull(sum(qty$padded),0) qty$padded";
    }


    $sql = <<<EOF
SELECT bonusset, month $sqlCols
FROM `tblItemHistoryMonthly` ihm
join tblRealm r on ihm.house = r.house and r.region = ?
WHERE ihm.item=?
group by bonusset, month
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('si', $region, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $bonusRows = DBMapArray($result, array('bonusset', null));
    $stmt->close();

    $tr = array();
    foreach ($bonusRows as $bonusSet => &$rows) {
        $prevPrice = 0;
        for ($x = 0; $x < count($rows); $x++) {
            $year = 2014 + floor(($rows[$x]['month'] - 1) / 12);
            $monthNum = $rows[$x]['month'] % 12;
            if ($monthNum == 0) {
                $monthNum = 12;
            }
            $month = ($monthNum < 10 ? '0' : '') . $monthNum;
            for ($dayNum = 1; $dayNum <= 31; $dayNum++) {
                $day = ($dayNum < 10 ? '0' : '') . $dayNum;
                if (!is_null($rows[$x]['mkt' . $day])) {
                    $tr[$bonusSet][] = array('date'     => "$year-$month-$day",
                                  'silver'   => round($rows[$x]['mkt' . $day] / 100, 2),
                                  'quantity' => $rows[$x]['qty' . $day]
                    );
                    $prevPrice = round($rows[$x]['mkt' . $day] / 100, 2);
                } else {
                    if (!checkdate($monthNum, $dayNum, $year)) {
                        break;
                    }
                    if (strtotime("$year-$month-$day") > time()) {
                        break;
                    }
                    if ($prevPrice) {
                        $tr[$bonusSet][] = array('date' => "$year-$month-$day", 'silver' => $prevPrice, 'quantity' => 0);
                    }
                }
            }
        }
    }
    unset($rows);

    MCSet($key, $tr);

    return $tr;
}
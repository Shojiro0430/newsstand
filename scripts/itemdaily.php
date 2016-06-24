<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');

RunMeNTimes(1);
CatchKill();

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

if (APIMaintenance()) {
    DebugMessage('API Maintenance in progress, not updating daily items!', E_USER_NOTICE);
    exit;
}

AddDailyData();
DebugMessage('Done! Started ' . TimeDiff($startTime));

function AddDailyData()
{
    global $db, $caughtKill;

    if ($caughtKill) {
        return;
    }

    $sql = <<<EOF
select s2.house, date(max(s2.updated)) `start`, max(s2.updated) `end`
from (
    select house
    from tblHouseCheck hc
    where ifnull(lastdaily,'2000-01-01') < date(timestampadd(day,-1,now()))
    and exists (select 1 from tblSnapshot s where updated >= date(now()) and s.house=hc.house and s.flags & 1 = 0)) aa
join tblSnapshot s2 on s2.house = aa.house
where s2.updated < date(now())
and s2.flags & 1 = 0
group by s2.house
EOF;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = DBMapArray($result);
    $stmt->close();

    DebugMessage(count($houses) . " houses need updates");

    $sqlPattern = <<<EOF
replace into tblItemHistoryDaily
(select id, %1\$d, '%2\$s', round(pricemin/100), round(priceavg/100), round(pricemax/100), round(pricestart/100), round(priceend/100), quantitymin, quantityavg, quantitymax from (
    select i.id,
    min(price) pricemin,
    round(avg(price)) priceavg,
    max(price) pricemax,
    min(if(@previtem != i.id, price, null)) pricestart,
    min(cast(if(sn.updated = '%3\$s', @lastprice, null) as decimal(11,0))) priceend,
    min(ifnull(quantity,0)) quantitymin,
    avg(ifnull(quantity,0)) quantityavg,
    max(quantity) quantitymax
    from (select @previtem := 0, @lastprice := 0) itemsetup, tblSnapshot sn
    join tblDBCItem i
    left join tblItemHistory ih on ih.house=%1\$d and sn.updated=ih.snapshot and ih.item=i.id and ih.bonusset=0
    where sn.house=%4\$d
    and sn.updated between '%2\$s' and '%3\$s'
    and sn.flags & 1 = 0
    and i.stacksize > 1
    group by i.id
    order by i.id, sn.updated) aa
where quantitymax > 0);
EOF;

    foreach ($houses as $house => $houseRow) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        if (!MCHouseLock($house)) {
            continue;
        }

        $sql = sprintf($sqlPattern, $house, $houseRow['start'], $houseRow['end'], $house);
        $db->real_query($sql);
        $rowCount = $db->affected_rows;

        DebugMessage("$rowCount item daily rows updated for house $house for date {$houseRow['start']}");

        $stmt = $db->prepare('INSERT INTO tblHouseCheck (house, lastdaily) VALUES (?, ?) ON DUPLICATE KEY UPDATE lastdaily = values(lastdaily)');
        $stmt->bind_param('is', $house, $houseRow['start']);
        $stmt->execute();
        $stmt->close();

        MCHouseUnlock($house);
    }
}
<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['species'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);
$species = intval($_GET['species'], 10);

if (!$species) {
    json_return(array());
}

BotCheck();
HouseETag($house);

$json = array(
    'stats'     => PetStats($house, $species),
    'history'   => PetHistory($house, $species),
    'auctions'  => PetAuctions($house, $species),
    'globalnow' => PetGlobalNow(GetRegion($house), $species),
);

json_return($json);

function PetStats($house, $species)
{
    global $db;

    $key = 'battlepet_stats_l_' . $species;
    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $names = LocaleColumns('i.name');
    $sql = <<<EOF
select i.id, $names, i.icon, i.type, i.npc,
s.price, s.quantity, s.lastseen, s.breed
from tblDBCPet i
left join tblPetSummary s on s.house = ? and s.species = i.id
where i.id = ?
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    foreach ($tr as &$breedRow) {
        $breedRow = array_pop($breedRow);
    }
    unset($breedRow);
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function PetHistory($house, $species)
{
    global $db;

    $key = 'battlepet_history2_' . $species;
    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $historyDays = HISTORY_DAYS;

    $sql = <<<EOF
select breed, snapshot, silver*100 price, quantity
from (
    select if(breed = @prevBreed, null, @price := null) resetprice, @prevBreed := breed as breed, unix_timestamp(updated) snapshot,
        cast(if(quantity is null, @price, @price := silver) as unsigned) `silver`, ifnull(quantity,0) as quantity
    from (select @price := null, @prevBreed := null) priceSetup, 
    (select ps.breed, s.updated, 
        case hour(s.updated)
            when  0 then ph.quantity00 when  1 then ph.quantity01 when  2 then ph.quantity02 when  3 then ph.quantity03
            when  4 then ph.quantity04 when  5 then ph.quantity05 when  6 then ph.quantity06 when  7 then ph.quantity07
            when  8 then ph.quantity08 when  9 then ph.quantity09 when 10 then ph.quantity10 when 11 then ph.quantity11
            when 12 then ph.quantity12 when 13 then ph.quantity13 when 14 then ph.quantity14 when 15 then ph.quantity15
            when 16 then ph.quantity16 when 17 then ph.quantity17 when 18 then ph.quantity18 when 19 then ph.quantity19
            when 20 then ph.quantity20 when 21 then ph.quantity21 when 22 then ph.quantity22 when 23 then ph.quantity23
            else null end as `quantity`,
        case hour(s.updated)
            when  0 then ph.silver00 when  1 then ph.silver01 when  2 then ph.silver02 when  3 then ph.silver03
            when  4 then ph.silver04 when  5 then ph.silver05 when  6 then ph.silver06 when  7 then ph.silver07
            when  8 then ph.silver08 when  9 then ph.silver09 when 10 then ph.silver10 when 11 then ph.silver11
            when 12 then ph.silver12 when 13 then ph.silver13 when 14 then ph.silver14 when 15 then ph.silver15
            when 16 then ph.silver16 when 17 then ph.silver17 when 18 then ph.silver18 when 19 then ph.silver19
            when 20 then ph.silver20 when 21 then ph.silver21 when 22 then ph.silver22 when 23 then ph.silver23
            else null end as `silver`
    from tblSnapshot s
    join tblPetSummary ps on ps.house = ?
    left join tblPetHistoryHourly ph on date(s.updated) = ph.`when` and ph.house = ps.house and ph.species = ps.species and ph.breed = ps.breed
    where s.house = ? and ps.species = ? and s.updated >= timestampadd(day,-$historyDays,now()) and s.flags & 1 = 0
    order by ps.breed, s.updated asc
    ) ordered
) withoutresets
EOF;

    $stmt = $db->prepare($sql);
    $realHouse = abs($house);
    $stmt->bind_param('iii', $house, $realHouse, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    $stmt->close();

    foreach ($tr as &$breedSet) {
        while (count($breedSet) > 0 && is_null($breedSet[0]['price'])) {
            array_shift($breedSet);
        }
    }
    unset($breedSet);

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function PetAuctions($house, $species)
{
    global $db;

    $key = 'battlepet_auctions2_' . $species;
    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
SELECT ap.breed, quantity, bid, buy, ap.level, ap.quality, s.realm sellerrealm, ifnull(s.name, '???') sellername
FROM `tblAuction` a
JOIN `tblAuctionPet` ap on a.house = ap.house and a.id = ap.id
left join tblSeller s on a.seller=s.id
WHERE a.house=? and a.item=82800 and ap.species=?
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function PetGlobalNow($region, $species)
{
    global $db;

    $key = 'battlepet_globalnow2_' . $region . '_' . $species;
    if (($tr = MCGet($key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
    SELECT i.breed, r.house, i.price, i.quantity, unix_timestamp(i.lastseen) as lastseen
FROM `tblPetSummary` i
join tblRealm r on i.house = r.house and r.region = ?
WHERE i.species=?
group by i.breed, r.house
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('si', $region, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    $stmt->close();

    MCSet($key, $tr);

    return $tr;
}
<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['search'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);
$search = strtolower(substr(trim($_GET['search']), 0, 50));

if ($search == '') {
    json_return(array());
}

BotCheck();
HouseETag($house);

if ($json = MCGetHouse($house, 'search_' . $search)) {
    json_return($json);
}

DBConnect();

$json = array(
    'items'      => SearchItems($house, $search),
    'sellers'    => SearchSellers($house, $search),
    'battlepets' => SearchBattlePets($house, $search),
);

$ak = array_keys($json);
foreach ($ak as $k) {
    if (count($json[$k]) == 0) {
        unset($json[$k]);
    }
}

$json = json_encode($json, JSON_NUMERIC_CHECK);

MCSetHouse($house, 'search_' . $search, $json);

json_return($json);

function SearchItems($house, $search)
{
    global $db;

    $suffixes = MCGet('search_itemsuffixes');
    if ($suffixes === false) {
        $stmt = $db->prepare('SELECT lower(suffix) FROM tblDBCItemRandomSuffix');
        $stmt->execute();
        $result = $stmt->get_result();
        $suffixes = DBMapArray($result, null);
        $stmt->close();

        MCSet('search_itemsuffixes', $suffixes, 86400);
    }

    $terms = preg_replace('/\s+/', '%', " $search ");
    $nameSearch = 'i.name like ?';

    $terms2 = '';

    $barewords = trim(preg_replace('/ {2,}/', ' ', preg_replace('/[^ a-zA-Z0-9\'\.]/', '', $search)));

    for ($x = 0; $x < count($suffixes); $x++) {
        if (substr($barewords, -1 * strlen($suffixes[$x])) == $suffixes[$x]) {
            $terms2 = '%' . str_replace(' ', '%', substr($barewords, 0, -1 * strlen($suffixes[$x]) - 1)) . '%';
            $nameSearch = '(i.name like ? or i.name like ?)';
        }
    }

    $sql = <<<EOF
select i.id, i.name, i.quality, i.icon, i.class as classid, s.price, s.quantity, unix_timestamp(s.lastseen) lastseen, round(avg(h.price)) avgprice
from tblDBCItem i
left join tblItemSummary s on s.house=? and s.item=i.id
left join tblItemHistory h on h.house=? and h.item=i.id
where $nameSearch
and ifnull(i.auctionable,1) = 1
group by i.id
limit ?
EOF;
    $limit = 50 * strlen(preg_replace('/\s/', '', $search));

    $stmt = $db->prepare($sql);
    if ($terms2 == '') {
        $stmt->bind_param('iisi', $house, $house, $terms, $limit);
    } else {
        $stmt->bind_param('iissi', $house, $house, $terms, $terms2, $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    return $tr;
}

function SearchSellers($house, $search)
{
    global $db;

    $terms = mb_ereg_replace('\s+', '%', " $search ");

    $sql = <<<EOF
select s.id, r.id realm, s.name, unix_timestamp(s.firstseen) firstseen, unix_timestamp(s.lastseen) lastseen
from tblSeller s
join tblRealm r on s.realm=r.id and r.house=?
where convert(s.name using utf8) collate utf8_unicode_ci like ?
limit 50
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $house, $terms);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    return $tr;
}

function SearchBattlePets($house, $search)
{
    global $db;

    $terms = preg_replace('/\s+/', '%', " $search ");

    $sql = <<<EOF
select i.id, i.name, i.icon, i.type, i.npc,
min(if(s.quantity>0,s.price,null)) price, sum(s.quantity) quantity, unix_timestamp(max(s.lastseen)) lastseen,
(select round(avg(h.price)) from tblPetHistory h where h.house=? and h.species=i.id group by h.breed order by 1 asc limit 1) avgprice
from tblPet i
left join tblPetSummary s on s.house=? and s.species=i.id
where i.name like ?
group by i.id
limit ?
EOF;
    $limit = 50 * strlen(preg_replace('/\s/', '', $search));

    $stmt = $db->prepare($sql);
    $stmt->bind_param('iisi', $house, $house, $terms, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    return $tr;
}

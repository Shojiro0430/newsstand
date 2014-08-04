<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['search']))
    json_return(array());

$house = intval($_GET['house'], 10);
$search = strtolower(substr(trim($_GET['search']), 0, 50));

if ($search == '')
    json_return(array());

if ($json = MCGetHouse($house, 'search_'.$search))
    json_return($json);

DBConnect();

$json = array(
    'items' => SearchItems($house, $search),
    'sellers' => SearchSellers($house, $search),
);

$ak = array_keys($json);
foreach ($ak as $k)
    if (count($json[$k]) == 0)
        unset($json[$k]);

$json = json_encode($json, JSON_NUMERIC_CHECK);

MCSetHouse($house, 'search_'.$search, $json);

json_return($json);

function SearchItems($house, $search)
{
    global $db;

    $terms = preg_replace('/\s+/', '%', " $search ");

    $sql = <<<EOF
select i.id, i.name, i.quality, i.icon, i.class as classid, s.price, s.quantity, unix_timestamp(s.lastseen) lastseen
from tblItem i
left join tblItemSummary s on s.house=? and s.item=i.id
where i.name like ?
and ifnull(i.auctionable,1) = 1
order by i.class, i.name
limit 200
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $house, $terms);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result);
    $stmt->close();
    return $tr;
}

function SearchSellers($house, $search)
{
    global $db;

    $terms = preg_replace('/\s+/', '%', " $search ");
    $house = abs($house);

    $sql = <<<EOF
select s.id, r.id realm, s.name, unix_timestamp(s.firstseen) firstseen, unix_timestamp(s.lastseen) lastseen
from tblSeller s
join tblRealm r on s.realm=r.id and r.house=?
where s.name like ?
order by s.name, r.name
limit 50
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $house, $terms);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result);
    $stmt->close();
    return $tr;
}
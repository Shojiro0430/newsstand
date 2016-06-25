<?php
require_once('../incl/old.incl.php');
require_once('dbcdecode.php');

header('Content-type: text/plain');
error_reporting(E_ALL);

$dirnm = 'current/enUS';

DBConnect();

$tables = array();
dtecho(run_sql('set session max_heap_table_size='.(1024*1024*1024)));

dtecho(dbcdecode('ItemSubClass', array(1=>'id', 2=>'class', 3=>'subclass', 12=>'name', 13=>'plural')));
dtecho(run_sql('truncate tblDBCItemSubClass'));
dtecho(run_sql('insert ignore into tblDBCItemSubClass (class, subclass, name_enus) (select class, subclass, if(ifnull(plural,\'\')=\'\',name,plural) from ttblItemSubClass)'));

dtecho(dbcdecode('FileData', array(1=>'id', 2=>'name')));
dtecho(dbcdecode('BattlePetSpecies', array(1=>'id', 2=>'npcid', 3=>'iconid', 5=>'type', 6=>'category', 7=>'flags')));
dtecho(dbcdecode('BattlePetSpeciesState', array(1=>'id', 2=>'speciesid', 3=>'stateid', 4=>'amount')));
dtecho(dbcdecode('Creature', array(1=>'id', 15=>'name')));

dtecho(run_sql('truncate tblDBCPet'));
$sql = <<<EOF
insert into tblDBCPet (id, name_enus, type, icon, npc, category, flags)
(select bps.id, c.name, bps.type, if(right(lower(fd.name), 4) = '.blp', lower(substr(fd.name, 1, length(fd.name) - 4)), lower(fd.name)), bps.npcid, bps.category, bps.flags
from ttblBattlePetSpecies bps
join ttblCreature c on bps.npcid = c.id
join ttblFileData fd on bps.iconid = fd.id)
EOF;
dtecho(run_sql($sql));

dtecho(run_sql('update tblDBCPet p set power=(select bpss.amount from ttblBattlePetSpeciesState bpss where bpss.speciesid=p.id and bpss.stateid=18)'));
dtecho(run_sql('update tblDBCPet p set stamina=(select bpss.amount from ttblBattlePetSpeciesState bpss where bpss.speciesid=p.id and bpss.stateid=19)'));
dtecho(run_sql('update tblDBCPet p set speed=(select bpss.amount from ttblBattlePetSpeciesState bpss where bpss.speciesid=p.id and bpss.stateid=20)'));

dtecho(dbcdecode('ItemBonus', array(2=>'bonusid', 3=>'changetype', 4=>'param1', 5=>'param2', 6=>'prio')));;
dtecho(dbcdecode('ItemNameDescription', array(1=>'id', 2=>'name')));

$bonuses = [];
$bonusNames = [];
$rst = get_rst('select * from ttblItemNameDescription');
while ($row = next_row($rst)) {
    $bonusNames[$row['id']] = $row['name'];
}
$rst = get_rst('select * from ttblItemBonus order by bonusid, prio');
while ($row = next_row($rst)) {
    if (!isset($bonuses[$row['bonusid']])) {
        $bonuses[$row['bonusid']] = [];
    }
    switch ($row['changetype']) {
        case 1: // itemlevel
            if (!isset($bonuses[$row['bonusid']]['itemlevel'])) {
                $bonuses[$row['bonusid']]['itemlevel'] = 0;
            }
            $bonuses[$row['bonusid']]['itemlevel'] += $row['param1'];
            break;
        case 3: // quality
            $bonuses[$row['bonusid']]['quality'] = $row['param1'];
            break;
        case 4: // nametag
            if (!isset($bonuses[$row['bonusid']]['nametag'])) {
                $bonuses[$row['bonusid']]['nametag'] = ['name' => '', 'prio' => -1];
            }
            if ($bonuses[$row['bonusid']]['nametag']['prio'] < $row['param2']) {
                $bonuses[$row['bonusid']]['nametag'] = ['name' => isset($bonusNames[$row['param1']]) ? $bonusNames[$row['param1']] : $row['param1'], 'prio' => $row['param2']];
            }
            break;
        case 5: // rand enchant name
            if (!isset($bonuses[$row['bonusid']]['randname'])) {
                $bonuses[$row['bonusid']]['randname'] = ['name' => '', 'prio' => -1];
            }
            if ($bonuses[$row['bonusid']]['randname']['prio'] < $row['param2']) {
                $bonuses[$row['bonusid']]['randname'] = ['name' => isset($bonusNames[$row['param1']]) ? $bonusNames[$row['param1']] : $row['param1'], 'prio' => $row['param2']];
            }
            break;
    }
}

dtecho(run_sql('truncate table tblDBCItemBonus'));
foreach ($bonuses as $bonusId => $bonusData) {
    $sql = "insert into tblDBCItemBonus (id, quality, `level`, tag_enus, tagpriority, `name_enus`, namepriority) values ($bonusId";
    if (isset($bonusData['quality'])) {
        $sql .= ', ' . $bonusData['quality'];
    } else {
        $sql .= ', null';
    }
    if (isset($bonusData['itemlevel']) && $bonusData['itemlevel']) {
        $sql .= ', ' . $bonusData['itemlevel'];
    } else {
        $sql .= ', null';
    }
    if (isset($bonusData['nametag'])) {
        $sql .= ', \'' . sql_esc($bonusData['nametag']['name']) . '\', ' . $bonusData['nametag']['prio'];
    } else {
        $sql .= ', null, null';
    }
    if (isset($bonusData['randname'])) {
        $sql .= ', \'' . sql_esc($bonusData['randname']['name']) . '\', ' . $bonusData['randname']['prio'];
    } else {
        $sql .= ', null, null';
    }
    $sql .= ')';
    dtecho(run_sql($sql));
}
unset($bonuses, $bonusNames, $bonusId, $bonusData);
dtecho(run_sql('update tblDBCItemBonus set flags = flags | 1 where ifnull(level,0) != 0'));

dtecho(dbcdecode('Item', array(1=>'id', 2=>'classid', 3=>'subclassid', 8=>'iconfiledataid')));
dtecho(dbcdecode('Item-sparse', array(
    1=>'id',
    2=>'quality',
    4=>'flags2',
    8=>'buycount',
    9=>'buyprice',
    10=>'sellprice',
    11=>'type',
    14=>'level',
    15=>'requiredlevel',
    16=>'requiredskill',
    24=>'stacksize',
    26=>'stat1',
    27=>'stat2',
    28=>'stat3',
    29=>'stat4',
    30=>'stat5',
    31=>'stat6',
    32=>'stat7',
    33=>'stat8',
    34=>'stat9',
    35=>'stat10',
    70=>'binds',
    71=>'name')));

dtecho('Running items..');
//dtecho(run_sql('truncate table tblDBCItem'));
$sql = <<<EOF
insert into tblDBCItem (id, name_enus, quality, level, class, subclass, icon, stacksize, binds,
buyfromvendor, selltovendor, auctionable, type, requiredlevel, requiredskill, flags)
(select i.id, ifnull(s.name,''), ifnull(s.quality,0), s.level, i.classid, i.subclassid,
if(right(lower(fd.name), 4) = '.blp', lower(substr(fd.name, 1, length(fd.name) - 4)), lower(fd.name)),
s.stacksize, s.binds, s.buyprice, s.sellprice, case s.binds when 0 then 1 when 2 then 1 when 3 then 1 else 0 end,
s.type, s.requiredlevel, s.requiredskill,
if(stat1 <= 0 and stat2 <= 0 and stat3 <= 0 and stat4 <= 0 and stat5 <= 0 and stat6 <= 0 and stat7 <= 0 and stat8 <= 0 and stat9 <= 0 and stat10 <= 0, 0, if (
 stat1 in (35,57) or
 stat2 in (35,57) or
 stat3 in (35,57) or
 stat4 in (35,57) or
 stat5 in (35,57) or
 stat6 in (35,57) or
 stat7 in (35,57) or
 stat8 in (35,57) or
 stat9 in (35,57) or
 stat10 in (35,57), 1, 0) | if(s.flags2 & 0x400000 > 0, 2, 0))
from ttblItem i
join `ttblItem-sparse` s on s.id = i.id
left join ttblFileData fd on fd.id = i.iconfiledataid)
on duplicate key update
tblDBCItem.quality=if(values(name)='',tblDBCItem.quality,values(quality)),
tblDBCItem.level=ifnull(values(level),tblDBCItem.level),
tblDBCItem.class=values(class),
tblDBCItem.subclass=values(subclass),
tblDBCItem.icon=values(icon),
tblDBCItem.stacksize=ifnull(values(stacksize), tblDBCItem.stacksize),
tblDBCItem.binds=ifnull(values(binds), tblDBCItem.binds),
tblDBCItem.buyfromvendor=ifnull(values(buyfromvendor), tblDBCItem.buyfromvendor),
tblDBCItem.selltovendor=ifnull(values(selltovendor), tblDBCItem.selltovendor),
tblDBCItem.auctionable=ifnull(values(auctionable), tblDBCItem.auctionable),
tblDBCItem.type=ifnull(values(type), tblDBCItem.type),
tblDBCItem.requiredlevel=ifnull(values(requiredlevel), tblDBCItem.requiredlevel),
tblDBCItem.requiredskill=ifnull(values(requiredskill), tblDBCItem.requiredskill),
tblDBCItem.flags=if(values(name)='',tblDBCItem.flags,values(flags)),
tblDBCItem.name_enus=if(values(name_enus)='',tblDBCItem.name_enus,values(name_enus))
EOF;
dtecho(run_sql($sql));

dtecho(dbcdecode('ItemModifiedAppearance', array(2=>'itemid', 3=>'bonustype', 4=>'appearanceid', 5=>'iconoverride', 6=>'idx')));
dtecho(dbcdecode('ItemAppearance', array(1=>'appearanceid', 2=>'display', 3=>'iconfiledataid')));

$sql = <<<EOF
update tblDBCItem i
set icon =
(select if(right(lower(fd.name), 4) = '.blp', lower(substr(fd.name, 1, length(fd.name) - 4)), lower(fd.name))
from ttblFileData fd
join ttblItemModifiedAppearance ima on ima.iconoverride=fd.id
where ima.itemid = i.id
order by if(ima.bonustype=0,0,1), ima.idx asc
limit 1)
where ifnull(icon,'') = ''
EOF;

dtecho(run_sql($sql));

$sql = <<<EOF
update tblDBCItem i
set icon =
(select if(right(lower(fd.name), 4) = '.blp', lower(substr(fd.name, 1, length(fd.name) - 4)), lower(fd.name))
from ttblFileData fd
join ttblItemAppearance ia on ia.iconfiledataid=fd.id
join ttblItemModifiedAppearance ima on ima.appearanceid=ia.appearanceid
where ima.itemid = i.id
order by if(ima.bonustype=0,0,1), ima.idx asc
limit 1)
where ifnull(icon,'') = ''
EOF;

dtecho(run_sql($sql));

$sql = <<<EOF
update tblDBCItem i
set display =
(select ia.display
from ttblItemAppearance ia
join ttblItemModifiedAppearance ima on ima.appearanceid=ia.appearanceid
where ima.itemid = i.id
order by if(ima.bonustype=0,0,1), ima.idx asc
limit 1)
where display is null
EOF;

dtecho(run_sql($sql));

dtecho(dbcdecode('ItemXBonusTree', array(2=>'itemid', 3=>'nodeid')));
dtecho(dbcdecode('ItemBonusTreeNode', array(2=>'nodeid', 5=>'bonusid')));

$sql = <<<EOF
update tblDBCItem i
set basebonus = (
    select ib.id
    from tblDBCItemBonus ib
    join ttblItemBonusTreeNode btn on btn.bonusid=ib.id
    join ttblItemXBonusTree ibt on ibt.nodeid=btn.nodeid
    where ibt.itemid = i.id
    and ib.level is null
    and ib.tag is not null
    order by ib.tagpriority desc
    limit 1
);
EOF;
dtecho(run_sql($sql));

dtecho(dbcdecode('ItemEffect', array(2=>'itemid', 4=>'spellid')));
dtecho(run_sql('truncate table tblDBCItemSpell'));
dtecho(run_sql('insert ignore into tblDBCItemSpell (select * from ttblItemEffect where itemid > 0 and spellid > 0)'));

dtecho(dbcdecode('ItemRandomSuffix', array(1=>'suffixid', 2=>'name1', 3=>'name2')));
dtecho(dbcdecode('ItemRandomProperties', array(1=>'suffixid', 2=>'name1', 8=>'name2')));
dtecho(run_sql('truncate table tblDBCRandEnchants'));
dtecho(run_sql('insert into tblDBCRandEnchants (id, name_enus) (select suffixid, ifnull(name1, name2) from ttblItemRandomProperties)'));
dtecho(run_sql('insert into tblDBCRandEnchants (id, name_enus) (select suffixid * -1, ifnull(name1, name2) from ttblItemRandomSuffix)'));
dtecho(run_sql('truncate table tblDBCItemRandomSuffix'));
dtecho(run_sql('insert into tblDBCItemRandomSuffix (locale, suffix) (select distinct \'enus\', name from tblDBCRandEnchants where trim(name) like \'of %\')'));

/*
dtecho(dbcdecode('ItemToBattlePet', array(1=>'itemid',2=>'speciesid')));
dtecho(run_sql('truncate table tblDBCItemToBattlePet'));
dtecho(run_sql('insert ignore into tblDBCItemToBattlePet (select * from ttblItemToBattlePet)'));
*/

dtecho(dbcdecode('SpellIcon', array(1=>'iconid',2=>'iconpath')));
dtecho(run_sql('update ttblSpellIcon set iconpath = substring_index(iconpath,\'\\\\\',-1) where instr(iconpath,\'\\\\\') > 0'));

dtecho(dbcdecode('SpellEffect', array(
	1=>'effectid',
	3=>'effecttypeid', //24 = create item, 53 = enchant, 157 = create tradeskill item
	7=>'qtymade',
	11=>'diesides',
	12=>'itemcreated',
	28=>'spellid',
	29=>'effectorder'
	)));

dtecho(dbcdecode('Spell', array(
	1=>'spellid',
	2=>'spellname',
	4=>'longdescription',
	24=>'miscid',
	19=>'reagentsid',
    15=>'cooldownsid',
    13=>'categoriesid',
	)));

dtecho(dbcdecode('SpellCooldowns', array(
            1=>'id',
            4=>'categorycooldown',
            5=>'individualcooldown',
        )));

dtecho(dbcdecode('SpellCategories', array(
            1=>'id',
            4=>'categoryid',
            10=>'chargecategoryid',
        )));

dtecho(dbcdecode('SpellCategory', array(
            1=>'id',
            2=>'flags',
            6=>'chargecooldown',
        )));

dtecho(run_sql('create temporary table ttblSpellCategory2 select * from ttblSpellCategory'));
$tables[] = 'SpellCategory2';

dtecho(dbcdecode('SpellMisc', array(
	1=>'miscid',
	2=>'spellid',
	22=>'iconid'
	)));


dtecho(dbcdecode('SpellReagents', array(
	1=>'reagentsid',
	2=>'reagent1',
	3=>'reagent2',
	4=>'reagent3',
	5=>'reagent4',
	6=>'reagent5',
	7=>'reagent6',
	8=>'reagent7',
	9=>'reagent8',
	10=>'reagentcount1',
	11=>'reagentcount2',
	12=>'reagentcount3',
	13=>'reagentcount4',
	14=>'reagentcount5',
	15=>'reagentcount6',
	16=>'reagentcount7',
	17=>'reagentcount8'
	)));


dtecho(dbcdecode('SkillLine', array(1=>'lineid',2=>'linecatid',3=>'linename')));
dtecho(dbcdecode('SkillLineAbility', array(1=>'slaid',2=>'lineid',3=>'spellid',9=>'greyat',10=>'yellowat')));

dtecho(run_sql('CREATE temporary TABLE `ttblDBCSkillLines` (`id` smallint unsigned NOT NULL, `name` char(50) NOT NULL, PRIMARY KEY (`id`)) ENGINE=memory'));
dtecho(run_sql('insert into ttblDBCSkillLines (select lineid, linename from ttblSkillLine where ((linecatid=11) or (linecatid=9 and (linename=\'Cooking\' or linename like \'Way of %\'))))'));

dtecho('Getting trades..');
dtecho(run_sql('truncate tblDBCItemReagents'));
for ($x = 1; $x <= 8; $x++) {
    $sql = 'insert into tblDBCItemReagents (select itemcreated, sl.id, sr.reagent'.$x.', sr.reagentcount'.$x.'/if(se.diesides=0,if(se.qtymade=0,1,se.qtymade),(se.qtymade * 2 + se.diesides + 1)/2), s.spellid, 1 from ttblSpell s, ttblSpellReagents sr, ttblSpellEffect se, ttblSkillLineAbility sla, ttblDBCSkillLines sl where sla.lineid=sl.id and sla.spellid=s.spellid and s.reagentsid=sr.reagentsid and s.spellid=se.spellid and se.itemcreated != 0 and sr.reagent'.$x.' != 0)';
    $sr = run_sql($sql);
    if ($sr != '') dtecho($sql."\n".$sr);
}

dtecho(run_sql('truncate tblDBCSpell'));
$sql = <<<EOF
insert into tblDBCSpell (id,name,icon,description,cooldown,qtymade,yellow,skillline,crafteditem)
(select distinct s.spellid, s.spellname, si.iconpath, s.longdescription,
    greatest(
        ifnull(cd.categorycooldown * if(c.flags & 8, 86400, 1),0),
        ifnull(cd.individualcooldown * if(c.flags & 8, 86400, 1),0),
        ifnull(cc.chargecooldown,0)) / 1000,
    if(se.itemcreated=0,0,if(se.diesides=0,if(se.qtymade=0,1,se.qtymade),(se.qtymade * 2 + se.diesides + 1)/2)),
    sla.yellowat,sla.lineid,if(se.itemcreated=0,null,se.itemcreated)
from ttblSpell s
left join ttblSpellMisc sm on s.miscid=sm.miscid
left join ttblSpellIcon si on si.iconid=sm.iconid
left join ttblSpellCooldowns cd on cd.id = s.cooldownsid
left join ttblSpellCategories cs on cs.id = s.categoriesid
left join ttblSpellCategory c on c.id = cs.categoryid
left join ttblSpellCategory2 cc on cc.id = cs.chargecategoryid
join tblDBCItemReagents ir on s.spellid=ir.spell
join ttblSpellEffect se on s.spellid=se.spellid
join ttblSkillLineAbility sla on s.spellid=sla.spellid
where se.effecttypeid in (24,53,157))
EOF;
dtecho(run_sql($sql));

$sql = 'insert ignore into tblDBCSpell (id,name,icon,description) ';
$sql .= ' (select distinct s.spellid, s.spellname, si.iconpath, s.longdescription ';
$sql .= ' from ttblSpell s left join ttblSpellMisc sm on s.miscid=sm.miscid left join ttblSpellIcon si on si.iconid=sm.iconid ';
$sql .= ' join tblDBCItemSpell dis on dis.spell=s.spellid) ';
dtecho(run_sql($sql));


$sql = <<<EOF
replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip)
select ic.id, ir.skillline, ir.reagent, ir.quantity, ir.spell, 0
from tblDBCItem ic, tblDBCItem ic2, tblDBCItemReagents ir
where ic.class=3 and ic.quality=2 and ic.name like 'Perfect %'
and ic2.class=3 and ic2.name = substr(ic.name,9)
and ic2.id=ir.item
EOF;
dtecho(run_sql($sql));

/*
$sql = 'insert into tblDBCItemReagents (select 45850, skilllineid, reagentid, quantity, spellid from tblDBCItemReagents where itemid=45854)';
echo "$sql\n\n".run_sql($sql)."\n---\n";
$sql = 'insert into tblDBCItemReagents (select 45851, skilllineid, reagentid, quantity, spellid from tblDBCItemReagents where itemid=45854)';
echo "$sql\n\n".run_sql($sql)."\n---\n";
$sql = 'insert into tblDBCItemReagents (select 45852, skilllineid, reagentid, quantity, spellid from tblDBCItemReagents where itemid=45854)';
echo "$sql\n\n".run_sql($sql)."\n---\n";
$sql = 'insert into tblDBCItemReagents (select 45853, skilllineid, reagentid, quantity, spellid from tblDBCItemReagents where itemid=45854)';
echo "$sql\n\n".run_sql($sql)."\n---\n";
*/
$sql = 'update tblDBCItemReagents set quantity=quantity*1000 where item in (select id from tblDBCItem where class=6)';
run_sql($sql);

//$sql = 'replace INTO tblItemVendorCost (itemid, copper) VALUES (52078, 0)';
//run_sql($sql);

/* arctic fur */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (44128,0,38425,10,-32515,0)';
run_sql($sql);

/* frozen orb swaps NPC 40160 */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (47556,0,43102,6,-40160,0)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (45087,0,43102,4,-40160,0)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (35623,0,43102,1,-40160,0)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (35624,0,43102,1,-40160,0)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (36860,0,43102,1,-40160,0)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (35625,0,43102,1,-40160,0)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (35627,0,43102,1,-40160,0)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (35622,0,43102,1,-40160,0)';
run_sql($sql);

$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (36908,0,43102,1,-40160,0)';
run_sql($sql);

/* spirit of harmony */
$sql = <<<EOF
replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values 
(72092,0,76061,0.05,-66678,0),
(72093,0,76061,0.05,-66678,0),
(72094,0,76061,0.2,-66678,0),
(72103,0,76061,0.2,-66678,0),
(72120,0,76061,0.05,-66678,0),
(72238,0,76061,0.5,-66678,0),
(72988,0,76061,0.05,-66678,0),
(74247,0,76061,1,-66678,0),
(74249,0,76061,0.05,-66678,0),
(74250,0,76061,0.2,-66678,0),
(76734,0,76061,1,-66678,0),
(79101,0,76061,0.05,-66678,0),
(79255,0,76061,1,-66678,0)
EOF;
run_sql($sql);

/* ink trader - currency is ink of dreams */
/* starlight ink uncommon */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (79255,0,79254,10,-33027,0)';
run_sql($sql);

/* inferno ink uncommon */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (61981,0,79254,10,-33027,0)';
run_sql($sql);

/* snowfall ink uncommon */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (43127,0,79254,10,-33027,0)';
run_sql($sql);

/* blackfallow ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (61978,0,79254,1,-33027,0)';
run_sql($sql);

/* celestial ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (43120,0,79254,1,-33027,0)';
run_sql($sql);

/* ethereal ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (43124,0,79254,1,-33027,0)';
run_sql($sql);

/* ink of the sea */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (43126,0,79254,1,-33027,0)';
run_sql($sql);

/* ivory ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (37101,0,79254,1,-33027,0)';
run_sql($sql);

/* jadefire ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (43118,0,79254,1,-33027,0)';
run_sql($sql);

/* lions ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (43116,0,79254,1,-33027,0)';
run_sql($sql);

/* midnight ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (39774,0,79254,1,-33027,0)';
run_sql($sql);

/* moonglow ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (39469,0,79254,1,-33027,0)';
run_sql($sql);

/* shimmering ink */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (43122,0,79254,1,-33027,0)';
run_sql($sql);

/* pristine hide */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) values (52980,0,56516,10,-50381,0)';
run_sql($sql);

/* imperial silk cooldown */
$sql = 'replace into tblDBCItemReagents (item, skillline, reagent, quantity, spell, fortooltip) VALUES (82447, 197, 82441, 8, 125557, 1)';
run_sql($sql);

/* spells deleted from game */
$sql = 'delete from tblDBCItemReagents where spell=74493 and item=52976 and skillline=165 and reagent=52977 and quantity=4';
run_sql($sql);
$sql = 'delete from tblDBCItemReagents where spell=28021 and item=22445 and skillline=333 and reagent=12363';
run_sql($sql);
run_sql('delete FROM tblDBCItemReagents WHERE spell in (102366,140040,140041)');

dtecho('Getting spell expansion IDs..');
$sql = <<<EOF
SELECT s.id, max(ic.level) mx, min(ic.level) mn
FROM tblDBCItemReagents ir, tblDBCItem ic, tblDBCSpell s
WHERE ir.spell=s.id
and ir.reagent=ic.id
and ic.level <= 100
and ic.id not in (select item from tblDBCItemVendorCost)
and s.expansion is null
group by s.id
EOF;

$rst = get_rst($sql);
while ($row = next_row($rst))
{
    $exp = 0;

    if (is_null($row['mx']))
        $exp = 'null';
    elseif ($row['mx'] > 90)
        $exp = 5; // wod
    elseif ($row['mx'] > 85)
        $exp = 4; // mop
    elseif ($row['mx'] > 80)
        $exp = 3; // cata
    elseif ($row['mx'] > 70)
        $exp = 2; // wotlk
    elseif ($row['mx'] > 60)
        $exp = 1; // bc
    elseif ($row['mn'] == 60)
        $exp = 1;

    run_sql(sprintf('update tblDBCSpell set expansion=%s where id=%d', $exp, $row['id']));
}


/* */
dtecho("Done.\n ");

function getIconById($iconid) {
	static $iconcache = array();
	if (isset($iconcache[$iconid])) return $iconcache[$iconid];

	$spellicon = '';
	$irow = get_single_row('select iconpath from ttblSpellIcon where iconid=\''.$iconid.'\'');
	if (($irow) && (nvl($irow['iconpath'],'~') != '~')) {
		$spellicon = $irow['iconpath'];
		$spellicon = substr($spellicon, strrpos($spellicon, '\\')+1);
	}
	$iconcache[$iconid] = $spellicon;
	return $spellicon;
}

function dtecho($msg) {
	if ($msg == '') return;
	if (substr($msg, -1, 1)=="\n") $msg = substr($msg, 0, -1);
	echo "\n".Date('H:i:s').' '.$msg;
}

foreach ($tables as $tbl) {
	run_sql('drop temporary table `ttbl'.$tbl.'`');
}
cleanup('');
?>

<?php

$startTime = time();

require_once('../incl/incl.php');

RunMeNTimes(1);

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

BuildRealmGuidHouse();

DebugMessage('Done! Started '.TimeDiff($startTime));

function BuildRealmGuidHouse()
{
    global $db;

    $guids = GetRealmInfo();
    $hardCoded = [
        1302 => 139, // EU "stonemaul" old realm -> EU archimonde house
        1396 => 147, // EU "molten core" old realm -> EU quel'thalas house
    ];

    $db->begin_transaction();

    $stmt = $db->prepare('SELECT region, lower(name) name, house FROM tblRealm');
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = DBMapArray($result, ['region', 'name']);
    $stmt->close();

    $db->query('delete from tblRealmGuidHouse');

    $stmt = $db->prepare('replace into tblRealmGuidHouse (realmguid, house) values (?, ?)');
    $realmGuid = 0;
    $house = 0;
    $stmt->bind_param('ii', $realmGuid, $house);

    foreach ($guids as $guid => $realmInfo) {
        $found = false;
        for ($x = 1; $x < count($realmInfo); $x++) {
            if (isset($houses[$realmInfo[0]][$realmInfo[$x]])) {
                $found = true;
                unset($hardCoded[$guid]);
                $realmGuid = $guid;
                $house = $houses[$realmInfo[0]][$realmInfo[$x]]['house'];
                $stmt->execute();
            }
        }
        if (!$found) {
            echo "Could not find house for ".implode(',', $realmInfo)."\n";
        }
    }

    foreach ($hardCoded as $guid => $forcedHouse) {
        $realmGuid = $guid;
        $house = $forcedHouse;
        $stmt->execute();
    }

    $stmt->close();

    $db->commit();

}

function GetRealmInfo()
{
    // https://github.com/Phanx/LibRealmInfo/blob/master/LibRealmInfo.lua

    $lua = <<<'EOF'
[1]="Lightbringer,PvE,enUS,US,PST",
[2]="Cenarius,PvE,enUS,US,PST",
[3]="Uther,PvE,enUS,US,PST",
[4]="Kilrogg,PvE,enUS,US,PST",
[5]="Proudmoore,PvE,enUS,US,PST",
[6]="Hyjal,PvE,enUS,US,PST",
[7]="Frostwolf,PvP,enUS,US,PST",
[8]="Ner'zhul,PvP,enUS,US,PST",
[9]="Kil'jaeden,PvP,enUS,US,PST",
[10]="Blackrock,PvP,enUS,US,PST",
[11]="Tichondrius,PvP,enUS,US,PST",
[12]="Silver Hand,RP,enUS,US,PST",
[13]="Doomhammer,PvE,enUS,US,MST",
[14]="Icecrown,PvE,enUS,US,MST",
[15]="Deathwing,PvP,enUS,US,MST",
[16]="Kel'Thuzad,PvP,enUS,US,MST",
[47]="Eitrigg,PvE,enUS,US,CST",
[51]="Garona,PvE,enUS,US,CST",
[52]="Alleria,PvE,enUS,US,CST",
[53]="Hellscream,PvE,enUS,US,CST",
[54]="Blackhand,PvE,enUS,US,CST",
[55]="Whisperwind,PvE,enUS,US,CST",
[56]="Archimonde,PvP,enUS,US,CST",
[57]="Illidan,PvP,enUS,US,CST",
[58]="Stormreaver,PvP,enUS,US,CST",
[59]="Mal'Ganis,PvP,enUS,US,CST",
[60]="Stormrage,PvE,enUS,US,EST",
[61]="Zul'jin,PvE,enUS,US,EST",
[62]="Medivh,PvE,enUS,US,EST",
[63]="Durotan,PvE,enUS,US,EST",
[64]="Bloodhoof,PvE,enUS,US,EST",
[65]="Khadgar,PvE,enUS,US,EST",
[66]="Dalaran,PvE,enUS,US,EST",
[67]="Elune,PvE,enUS,US,EST",
[68]="Lothar,PvE,enUS,US,EST",
[69]="Arthas,PvP,enUS,US,EST",
[70]="Mannoroth,PvP,enUS,US,EST",
[71]="Warsong,PvP,enUS,US,EST",
[72]="Shattered Hand,PvP,enUS,US,EST",
[73]="Bleeding Hollow,PvP,enUS,US,EST",
[74]="Skullcrusher,PvP,enUS,US,EST",
[75]="Argent Dawn,RP,enUS,US,EST",
[76]="Sargeras,PvP,enUS,US,CST",
[77]="Azgalor,PvP,enUS,US,CST",
[78]="Magtheridon,PvP,enUS,US,EST",
[79]="Destromath,PvP,enUS,US,PST",
[80]="Gorgonnash,PvP,enUS,US,PST",
[81]="Dethecus,PvP,enUS,US,PST",
[82]="Spinebreaker,PvP,enUS,US,PST",
[83]="Bonechewer,PvP,enUS,US,PST",
[84]="Dragonmaw,PvP,enUS,US,PST",
[85]="Shadowsong,PvE,enUS,US,PST",
[86]="Silvermoon,PvE,enUS,US,PST",
[87]="Windrunner,PvE,enUS,US,PST",
[88]="Cenarion Circle,RP,enUS,US,PST",
[89]="Nathrezim,PvP,enUS,US,MST",
[90]="Terenas,PvE,enUS,US,MST",
[91]="Burning Blade,PvP,enUS,US,EST",
[92]="Gorefiend,PvP,enUS,US,EST",
[93]="Eredar,PvP,enUS,US,EST",
[94]="Shadowmoon,PvP,enUS,US,EST",
[95]="Lightning's Blade,PvP,enUS,US,EST",
[96]="Eonar,PvE,enUS,US,EST",
[97]="Gilneas,PvE,enUS,US,EST",
[98]="Kargath,PvE,enUS,US,EST",
[99]="Llane,PvE,enUS,US,EST",
[100]="Earthen Ring,RP,enUS,US,EST",
[101]="Laughing Skull,PvP,enUS,US,CST",
[102]="Burning Legion,PvP,enUS,US,CST",
[103]="Thunderlord,PvP,enUS,US,CST",
[104]="Malygos,PvE,enUS,US,CST",
[105]="Thunderhorn,PvE,enUS,US,CST",
[106]="Aggramar,PvE,enUS,US,CST",
[107]="Crushridge,PvP,enUS,US,PST",
[108]="Stonemaul,PvP,enUS,US,PST",
[109]="Daggerspine,PvP,enUS,US,PST",
[110]="Stormscale,PvP,enUS,US,PST",
[111]="Dunemaul,PvP,enUS,US,PST",
[112]="Boulderfist,PvP,enUS,US,PST",
[113]="Suramar,PvE,enUS,US,PST",
[114]="Dragonblight,PvE,enUS,US,PST",
[115]="Draenor,PvE,enUS,US,PST",
[116]="Uldum,PvE,enUS,US,PST",
[117]="Bronzebeard,PvE,enUS,US,PST",
[118]="Feathermoon,RP,enUS,US,PST",
[119]="Bloodscalp,PvP,enUS,US,MST",
[120]="Darkspear,PvP,enUS,US,MST",
[121]="Azjol-Nerub,PvE,enUS,US,MST",
[122]="Perenolde,PvE,enUS,US,MST",
[123]="Eldre'Thalas,PvE,enUS,US,EST",
[124]="Spirestone,PvP,enUS,US,PST",
[125]="Shadow Council,RP,enUS,US,MST",
[126]="Scarlet Crusade,RP,enUS,US,CST",
[127]="Firetree,PvP,enUS,US,EST",
[128]="Frostmane,PvP,enUS,US,CST",
[129]="Gurubashi,PvP,enUS,US,PST",
[130]="Smolderthorn,PvP,enUS,US,EST",
[131]="Skywall,PvE,enUS,US,PST",
[151]="Runetotem,PvE,enUS,US,CST",
[153]="Moonrunner,PvE,enUS,US,PST",
[154]="Detheroc,PvP,enUS,US,CST",
[155]="Kalecgos,PvP,enUS,US,PST",
[156]="Ursin,PvP,enUS,US,PST",
[157]="Dark Iron,PvP,enUS,US,PST",
[158]="Greymane,PvE,enUS,US,CST",
[159]="Wildhammer,PvP,enUS,US,CST",
[160]="Staghelm,PvE,enUS,US,CST",
[162]="Emerald Dream,PvP RP,enUS,US,CST",
[163]="Maelstrom,PvP RP,enUS,US,CST",
[164]="Twisting Nether,PvP RP,enUS,US,CST",
[1067]="Cho'gall,PvP,enUS,US,CST",
[1068]="Gul'dan,PvP,enUS,US,CST",
[1069]="Kael'thas,PvE,enUS,US,CST",
[1070]="Alexstrasza,PvE,enUS,US,CST",
[1071]="Kirin Tor,RP,enUS,US,CST",
[1072]="Ravencrest,PvE,enUS,US,CST",
[1075]="Balnazzar,PvP,enUS,US,CST",
[1128]="Azshara,PvP,enUS,US,CST",
[1129]="Agamaggan,PvP,enUS,US,CST",
[1130]="Lightninghoof,PvP RP,enUS,US,CST",
[1131]="Nazjatar,PvP,enUS,US,PST",
[1132]="Malfurion,PvE,enUS,US,CST",
[1136]="Aegwynn,PvP,enUS,US,CST",
[1137]="Akama,PvP,enUS,US,CST",
[1138]="Chromaggus,PvP,enUS,US,CST",
[1139]="Draka,PvE,enUS,US,CST",
[1140]="Drak'thul,PvE,enUS,US,CST",
[1141]="Garithos,PvP,enUS,US,CST",
[1142]="Hakkar,PvP,enUS,US,CST",
[1143]="Khaz Modan,PvE,enUS,US,CST",
[1145]="Mug'thol,PvP,enUS,US,CST",
[1146]="Korgath,PvP,enUS,US,CST",
[1147]="Kul Tiras,PvE,enUS,US,CST",
[1148]="Malorne,PvP,enUS,US,CST",
[1151]="Rexxar,PvE,enUS,US,CST",
[1154]="Thorium Brotherhood,RP,enUS,US,CST",
[1165]="Arathor,PvE,enUS,US,PST",
[1173]="Madoran,PvE,enUS,US,CST",
[1175]="Trollbane,PvE,enUS,US,EST",
[1182]="Muradin,PvE,enUS,US,CST",
[1184]="Vek'nilash,PvE,enUS,US,CST",
[1185]="Sen'jin,PvE,enUS,US,CST",
[1190]="Baelgun,PvE,enUS,US,PST",
[1258]="Duskwood,PvE,enUS,US,EST",
[1259]="Zuluhed,PvP,enUS,US,EST",
[1260]="Steamwheedle Cartel,RP,enUS,US,EST",
[1262]="Norgannon,PvE,enUS,US,EST",
[1263]="Thrall,PvE,enUS,US,EST",
[1264]="Anetheron,PvP,enUS,US,EST",
[1265]="Turalyon,PvE,enUS,US,EST",
[1266]="Haomarush,PvP,enUS,US,EST",
[1267]="Scilla,PvP,enUS,US,EST",
[1268]="Ysondre,PvP,enUS,US,EST",
[1270]="Ysera,PvE,enUS,US,EST",
[1271]="Dentarg,PvE,enUS,US,EST",
[1276]="Andorhal,PvP,enUS,US,EST",
[1277]="Executus,PvP,enUS,US,EST",
[1278]="Dalvengyr,PvP,enUS,US,EST",
[1280]="Black Dragonflight,PvP,enUS,US,EST",
[1282]="Altar of Storms,PvP,enUS,US,EST",
[1283]="Uldaman,PvE,enUS,US,EST",
[1284]="Aerie Peak,PvE,enUS,US,PST",
[1285]="Onyxia,PvP,enUS,US,PST",
[1286]="Demon Soul,PvP,enUS,US,EST",
[1287]="Gnomeregan,PvE,enUS,US,PST",
[1288]="Anvilmar,PvE,enUS,US,PST",
[1289]="The Venture Co,PvP RP,enUS,US,PST",
[1290]="Sentinels,RP,enUS,US,PST",
[1291]="Jaedenar,PvP,enUS,US,EST",
[1292]="Tanaris,PvE,enUS,US,EST",
[1293]="Alterac Mountains,PvP,enUS,US,EST",
[1294]="Undermine,PvE,enUS,US,EST",
[1295]="Lethon,PvP,enUS,US,PST",
[1296]="Blackwing Lair,PvP,enUS,US,PST",
[1297]="Arygos,PvE,enUS,US,EST",
[1342]="Echo Isles,PvE,enUS,US,PST",
[1344]="The Forgotten Coast,PvP,enUS,US,EST",
[1345]="Fenris,PvE,enUS,US,EST",
[1346]="Anub'arak,PvP,enUS,US,EST",
[1347]="Blackwater Raiders,RP,enUS,US,PST",
[1348]="Vashj,PvP,enUS,US,PST",
[1349]="Korialstrasz,PvE,enUS,US,PST",
[1350]="Misha,PvE,enUS,US,PST",
[1351]="Darrowmere,PvE,enUS,US,PST",
[1352]="Ravenholdt,PvP RP,enUS,US,EST",
[1353]="Bladefist,PvE,enUS,US,PST",
[1354]="Shu'halo,PvE,enUS,US,PST",
[1355]="Winterhoof,PvE,enUS,US,CST",
[1356]="Sisters of Elune,RP,enUS,US,CST",
[1357]="Maiev,PvP,enUS,US,PST",
[1358]="Rivendare,PvP,enUS,US,PST",
[1359]="Nordrassil,PvE,enUS,US,PST",
[1360]="Tortheldrin,PvP,enUS,US,EST",
[1361]="Cairne,PvE,enUS,US,CST",
[1362]="Drak'Tharon,PvP,enUS,US,CST",
[1363]="Antonidas,PvE,enUS,US,PST",
[1364]="Shandris,PvE,enUS,US,EST",
[1365]="Moon Guard,RP,enUS,US,CST",
[1367]="Nazgrel,PvE,enUS,US,EST",
[1368]="Hydraxis,PvE,enUS,US,CST",
[1369]="Wyrmrest Accord,RP,enUS,US,PST",
[1370]="Farstriders,RP,enUS,US,CST",
[1371]="Borean Tundra,PvE,enUS,US,CST",
[1372]="Quel'dorei,PvE,enUS,US,CST",
[1373]="Garrosh,PvE,enUS,US,EST",
[1374]="Mok'Nathal,PvE,enUS,US,CST",
[1375]="Nesingwary,PvE,enUS,US,CST",
[1377]="Drenden,PvE,enUS,US,EST",
[1425]="Drakkari,PvP,esMX,US,CST",
[1427]="Ragnaros,PvP,esMX,US,CST",
[1428]="Quel'Thalas,PvE,esMX,US,CST",
[1549]="Azuremyst,PvE,enUS,US,PST",
[1555]="Auchindoun,PvP,enUS,US,EST",
[1556]="Coilfang,PvP,enUS,US,PST",
[1557]="Shattered Halls,PvP,enUS,US,PST",
[1558]="Blood Furnace,PvP,enUS,US,CST",
[1559]="The Underbog,PvP,enUS,US,CST",
[1563]="Terokkar,PvE,enUS,US,CST",
[1564]="Blade's Edge,PvE,enUS,US,PST",
[1565]="Exodar,PvE,enUS,US,EST",
[1566]="Area 52,PvE,enUS,US,EST",
[1567]="Velen,PvE,enUS,US,PST",
[1570]="The Scryers,RP,enUS,US,PST",
[1572]="Zangarmarsh,PvE,enUS,US,MST",
[1576]="Fizzcrank,PvE,enUS,US,CST",
[1578]="Ghostlands,PvE,enUS,US,CST",
[1579]="Grizzly Hills,PvE,enUS,US,CST",
[1581]="Galakrond,PvE,enUS,US,PST",
[1582]="Dawnbringer,PvE,enUS,US,CST",
[3207]="Goldrinn,PvE,ptBR,US,BRT",
[3208]="Nemesis,PvP,ptBR,US,BRT",
[3209]="Azralon,PvP,ptBR,US,BRT",
[3210]="Tol Barad,PvP,ptBR,US,BRT",
[3234]="Gallywix,PvE,ptBR,US,BRT",
[3721]="Caelestrasz,PvE,enUS,US,AEST",
[3722]="Aman'Thul,PvE,enUS,US,AEST",
[3723]="Barthilas,PvP,enUS,US,AEST",
[3724]="Thaurissan,PvP,enUS,US,AEST",
[3725]="Frostmourne,PvP,enUS,US,AEST",
[3726]="Khaz'goroth,PvE,enUS,US,AEST",
[3733]="Dreadmaul,PvP,enUS,US,AEST",
[3734]="Nagrand,PvE,enUS,US,AEST",
[3735]="Dath'Remar,PvE,enUS,US,AEST",
[3736]="Jubei'Thos,PvP,enUS,US,AEST",
[3737]="Gundrak,PvP,enUS,US,AEST",
[3738]="Saurfang,PvE,enUS,US,AEST",
[500]="Aggramar,PvE,enUS,EU",
[501]="Arathor,PvE,enUS,EU",
[502]="Aszune,PvE,enUS,EU",
[503]="Azjol-Nerub,PvE,enUS,EU",
[504]="Bloodhoof,PvE,enUS,EU",
[505]="Doomhammer,PvE,enUS,EU",
[506]="Draenor,PvE,enUS,EU",
[507]="Dragonblight,PvE,enUS,EU",
[508]="Emerald Dream,PvE,enUS,EU",
[509]="Garona,PvP,frFR,EU",
[510]="Vol'jin,PvE,frFR,EU",
[511]="Sunstrider,PvP,enUS,EU",
[512]="Arak-arahm,PvP,frFR,EU",
[513]="Twilight's Hammer,PvP,enUS,EU",
[515]="Zenedar,PvP,enUS,EU",
[516]="Forscherliga,RP,deDE,EU",
[517]="Medivh,PvE,frFR,EU",
[518]="Agamaggan,PvP,enUS,EU",
[519]="Al'Akir,PvP,enUS,EU",
[521]="Bladefist,PvP,enUS,EU",
[522]="Bloodscalp,PvP,enUS,EU",
[523]="Burning Blade,PvP,enUS,EU",
[524]="Burning Legion,PvP,enUS,EU",
[525]="Crushridge,PvP,enUS,EU",
[526]="Daggerspine,PvP,enUS,EU",
[527]="Deathwing,PvP,enUS,EU",
[528]="Dragonmaw,PvP,enUS,EU",
[529]="Dunemaul,PvP,enUS,EU",
[531]="Dethecus,PvP,deDE,EU",
[533]="Sinstralis,PvP,frFR,EU",
[535]="Durotan,PvE,deDE,EU",
[536]="Argent Dawn,RP,enUS,EU",
[537]="Kirin Tor,RP,frFR,EU",
[538]="Dalaran,PvE,frFR,EU",
[539]="Archimonde,PvP,frFR,EU",
[540]="Elune,PvE,frFR,EU",
[541]="Illidan,PvP,frFR,EU",
[542]="Hyjal,PvE,frFR,EU",
[543]="Kael'thas,PvP,frFR,EU",
[544]="Ner’zhul,PvP,frFR,EU,Ner'zhul",
[545]="Cho’gall,PvP,frFR,EU,Cho'gall",
[546]="Sargeras,PvP,frFR,EU",
[547]="Runetotem,PvE,enUS,EU",
[548]="Shadowsong,PvE,enUS,EU",
[549]="Silvermoon,PvE,enUS,EU",
[550]="Stormrage,PvE,enUS,EU",
[551]="Terenas,PvE,enUS,EU",
[552]="Thunderhorn,PvE,enUS,EU",
[553]="Turalyon,PvE,enUS,EU",
[554]="Ravencrest,PvP,enUS,EU",
[556]="Shattered Hand,PvP,enUS,EU",
[557]="Skullcrusher,PvP,enUS,EU",
[558]="Spinebreaker,PvP,enUS,EU",
[559]="Stormreaver,PvP,enUS,EU",
[560]="Stormscale,PvP,enUS,EU",
[561]="Earthen Ring,RP,enUS,EU",
[562]="Alexstrasza,PvE,deDE,EU",
[563]="Alleria,PvE,deDE,EU",
[564]="Antonidas,PvE,deDE,EU",
[565]="Baelgun,PvE,deDE,EU",
[566]="Blackhand,PvE,deDE,EU",
[567]="Gilneas,PvE,deDE,EU",
[568]="Kargath,PvE,deDE,EU",
[569]="Khaz'goroth,PvE,deDE,EU",
[570]="Lothar,PvE,deDE,EU",
[571]="Madmortem,PvE,deDE,EU",
[572]="Malfurion,PvE,deDE,EU",
[573]="Zuluhed,PvP,deDE,EU",
[574]="Nozdormu,PvE,deDE,EU",
[575]="Perenolde,PvE,deDE,EU",
[576]="Die Silberne Hand,RP,deDE,EU",
[577]="Aegwynn,PvP,deDE,EU",
[578]="Arthas,PvP,deDE,EU",
[579]="Azshara,PvP,deDE,EU",
[580]="Blackmoore,PvP,deDE,EU",
[581]="Blackrock,PvP,deDE,EU",
[582]="Destromath,PvP,deDE,EU",
[583]="Eredar,PvP,deDE,EU",
[584]="Frostmourne,PvP,deDE,EU",
[585]="Frostwolf,PvP,deDE,EU",
[586]="Gorgonnash,PvP,deDE,EU",
[587]="Gul'dan,PvP,deDE,EU",
[588]="Kel'Thuzad,PvP,deDE,EU",
[589]="Kil'jaeden,PvP,deDE,EU",
[590]="Mal'Ganis,PvP,deDE,EU",
[591]="Mannoroth,PvP,deDE,EU",
[592]="Zirkel des Cenarius,RP,deDE,EU",
[593]="Proudmoore,PvE,deDE,EU",
[594]="Nathrezim,PvP,deDE,EU",
[600]="Dun Morogh,PvE,deDE,EU",
[601]="Aman'thul,PvE,deDE,EU",
[602]="Sen'jin,PvE,deDE,EU",
[604]="Thrall,PvE,deDE,EU",
[605]="Theradras,PvP,deDE,EU",
[606]="Genjuros,PvP,enUS,EU",
[607]="Balnazzar,PvP,enUS,EU",
[608]="Anub'arak,PvP,deDE,EU",
[609]="Wrathbringer,PvP,deDE,EU",
[610]="Onyxia,PvP,deDE,EU",
[611]="Nera'thor,PvP,deDE,EU",
[612]="Nefarian,PvP,deDE,EU",
[613]="Kult der Verdammten,PvP RP,deDE,EU",
[614]="Das Syndikat,PvP RP,deDE,EU",
[615]="Terrordar,PvP,deDE,EU",
[616]="Krag'jin,PvP,deDE,EU",
[617]="Der Rat von Dalaran,RP,deDE,EU",
[618]="Nordrassil,PvE,enUS,EU",
[619]="Hellscream,PvE,enUS,EU",
[621]="Laughing Skull,PvP,enUS,EU",
[622]="Magtheridon,PvE,enUS,EU",
[623]="Quel'Thalas,PvE,enUS,EU",
[624]="Neptulon,PvP,enUS,EU",
[625]="Twisting Nether,PvP,enUS,EU",
[626]="Ragnaros,PvP,enUS,EU",
[627]="The Maelstrom,PvP,enUS,EU",
[628]="Sylvanas,PvP,enUS,EU",
[629]="Vashj,PvP,enUS,EU",
[630]="Bloodfeather,PvP,enUS,EU",
[631]="Darksorrow,PvP,enUS,EU",
[632]="Frostwhisper,PvP,enUS,EU",
[633]="Kor'gall,PvP,enUS,EU",
[635]="Defias Brotherhood,PvP RP,enUS,EU",
[636]="The Venture Co,PvP RP,enUS,EU",
[637]="Lightning's Blade,PvP,enUS,EU",
[638]="Haomarush,PvP,enUS,EU",
[639]="Xavius,PvP,enUS,EU",
[640]="Khaz Modan,PvE,frFR,EU",
[641]="Drek'Thar,PvE,frFR,EU",
[642]="Rashgarroth,PvP,frFR,EU",
[643]="Throk'Feroth,PvP,frFR,EU",
[644]="Conseil des Ombres,PvP RP,frFR,EU",
[645]="Varimathras,PvE,frFR,EU",
[646]="Hakkar,PvP,enUS,EU",
[647]="Les Sentinelles,RP,frFR,EU",
[1080]="Khadgar,PvE,enUS,EU",
[1081]="Bronzebeard,PvE,enUS,EU",
[1082]="Kul Tiras,PvE,enUS,EU",
[1083]="Chromaggus,PvP,enUS,EU",
[1084]="Dentarg,PvP,enUS,EU",
[1085]="Moonglade,RP,enUS,EU",
[1086]="La Croisade écarlate,PvP RP,frFR,EU",
[1087]="Executus,PvP,enUS,EU",
[1088]="Trollbane,PvP,enUS,EU",
[1089]="Mazrigos,PvE,enUS,EU",
[1090]="Talnivarr,PvP,enUS,EU",
[1091]="Emeriss,PvP,enUS,EU",
[1092]="Drak'thul,PvP,enUS,EU",
[1093]="Ahn'Qiraj,PvP,enUS,EU",
[1096]="Scarshield Legion,PvP RP,enUS,EU",
[1097]="Ysera,PvE,deDE,EU",
[1098]="Malygos,PvE,deDE,EU",
[1099]="Rexxar,PvE,deDE,EU",
[1104]="Anetheron,PvP,deDE,EU",
[1105]="Nazjatar,PvP,deDE,EU",
[1106]="Tichondrius,PvE,deDE,EU",
[1117]="Steamwheedle Cartel,RP,enUS,EU",
[1118]="Die ewige Wacht,RP,deDE,EU",
[1119]="Die Todeskrallen,PvP RP,deDE,EU",
[1121]="Die Arguswacht,PvP RP,deDE,EU",
[1122]="Uldaman,PvE,frFR,EU",
[1123]="Eitrigg,PvE,frFR,EU",
[1127]="Confrérie du Thorium,RP,frFR,EU",
[1298]="Vek'nilash,PvE,enUS,EU",
[1299]="Boulderfist,PvP,enUS,EU",
[1300]="Frostmane,PvP,enUS,EU",
[1301]="Outland,PvP,enUS,EU",
[1303]="Grim Batol,PvP,enUS,EU",
[1304]="Jaedenar,PvP,enUS,EU",
[1305]="Kazzak,PvP,enUS,EU",
[1306]="Tarren Mill,PvP,enUS,EU",
[1307]="Chamber of Aspects,PvE,enUS,EU",
[1308]="Ravenholdt,PvP RP,enUS,EU",
[1309]="Pozzo dell'Eternità,PvE,itIT,EU",
[1310]="Eonar,PvE,enUS,EU",
[1311]="Kilrogg,PvE,enUS,EU",
[1312]="Aerie Peak,PvE,enUS,EU",
[1313]="Wildhammer,PvE,enUS,EU",
[1314]="Saurfang,PvE,enUS,EU",
[1316]="Nemesis,PvP,itIT,EU",
[1317]="Darkmoon Faire,RP,enUS,EU",
[1318]="Vek'lor,PvP,deDE,EU",
[1319]="Mug'thol,PvP,deDE,EU",
[1320]="Taerar,PvP,deDE,EU",
[1321]="Dalvengyr,PvP,deDE,EU",
[1322]="Rajaxx,PvP,deDE,EU",
[1323]="Ulduar,PvE,deDE,EU",
[1324]="Malorne,PvE,deDE,EU",
[1326]="Der Abyssische Rat,PvP RP,deDE,EU",
[1327]="Der Mithrilorden,RP,deDE,EU",
[1328]="Tirion,PvE,deDE,EU",
[1330]="Ambossar,PvE,deDE,EU",
[1331]="Suramar,PvE,frFR,EU",
[1332]="Krasus,PvE,frFR,EU",
[1333]="Die Nachtwache,RP,deDE,EU",
[1334]="Arathi,PvP,frFR,EU",
[1335]="Ysondre,PvP,frFR,EU",
[1336]="Eldre'Thalas,PvP,frFR,EU",
[1337]="Culte de la Rive noire,PvP RP,frFR,EU",
[1378]="Dun Modr,PvP,esES,EU",
[1379]="Zul'jin,PvP,esES,EU",
[1380]="Uldum,PvP,esES,EU",
[1381]="C'Thun,PvP,esES,EU",
[1382]="Sanguino,PvP,esES,EU",
[1383]="Shen'dralar,PvP,esES,EU",
[1384]="Tyrande,PvE,esES,EU",
[1385]="Exodar,PvE,esES,EU",
[1386]="Minahonda,PvE,esES,EU",
[1387]="Los Errantes,PvE,esES,EU",
[1388]="Lightbringer,PvE,enUS,EU",
[1389]="Darkspear,PvE,enUS,EU",
[1391]="Alonsus,PvE,enUS,EU",
[1392]="Burning Steppes,PvP,enUS,EU",
[1393]="Bronze Dragonflight,PvE,enUS,EU",
[1394]="Anachronos,PvE,enUS,EU",
[1395]="Colinas Pardas,PvE,esES,EU",
[1400]="Un'Goro,PvE,deDE,EU",
[1401]="Garrosh,PvE,deDE,EU",
[1404]="Area 52,PvE,deDE,EU",
[1405]="Todeswache,RP,deDE,EU",
[1406]="Arygos,PvE,deDE,EU",
[1407]="Teldrassil,PvE,deDE,EU",
[1408]="Norgannon,PvE,deDE,EU",
[1409]="Lordaeron,PvE,deDE,EU",
[1413]="Aggra (Português),PvP,ptBR,EU",
[1415]="Terokkar,PvE,enUS,EU",
[1416]="Blade's Edge,PvE,enUS,EU",
[1417]="Azuremyst,PvE,enUS,EU",
[1587]="Hellfire,PvE,enUS,EU",
[1588]="Ghostlands,PvE,enUS,EU",
[1589]="Nagrand,PvE,enUS,EU",
[1595]="The Sha'tar,RP,enUS,EU",
[1596]="Karazhan,PvP,enUS,EU",
[1597]="Auchindoun,PvP,enUS,EU",
[1598]="Shattered Halls,PvP,enUS,EU",
[1602]="Гордунни,PvP,ruRU,EU,Gordunni",
[1603]="Король-лич,PvP,ruRU,EU,Lich King",
[1604]="Свежеватель Душ,PvP,ruRU,EU,Soulflayer",
[1605]="Страж Смерти,PvP,ruRU,EU,Deathguard",
[1606]="Sporeggar,PvP RP,enUS,EU",
[1607]="Nethersturm,PvE,deDE,EU",
[1608]="Shattrath,PvE,deDE,EU",
[1609]="Подземье,PvP,ruRU,EU,Deepholm",
[1610]="Седогрив,PvP,ruRU,EU,Greymane",
[1611]="Festung der Stürme,PvP,deDE,EU",
[1612]="Echsenkessel,PvP,deDE,EU",
[1613]="Blutkessel,PvP,deDE,EU",
[1614]="Галакронд,PvE,ruRU,EU,Galakrond",
[1615]="Ревущий фьорд,PvP,ruRU,EU,Howling Fjord",
[1616]="Разувий,PvP,ruRU,EU,Razuvious",
[1617]="Ткач Смерти,PvP,ruRU,EU,Deathweaver",
[1618]="Die Aldor,RP,deDE,EU",
[1619]="Das Konsortium,PvP RP,deDE,EU",
[1620]="Chants éternels,PvE,frFR,EU",
[1621]="Marécage de Zangar,PvE,frFR,EU",
[1622]="Temple noir,PvP,frFR,EU",
[1623]="Дракономор,PvE,ruRU,EU,Fordragon",
[1624]="Naxxramas,PvP,frFR,EU",
[1625]="Борейская тундра,PvE,ruRU,EU,Borean Tundra",
[1626]="Les Clairvoyants,RP,frFR,EU",
[1922]="Азурегос,PvE,ruRU,EU,Azuregos",
[1923]="Ясеневый лес,PvP,ruRU,EU,Ashenvale",
[1924]="Пиратская бухта,PvP,ruRU,EU,Booty Bay",
[1925]="Вечная Песня,PvE,ruRU,EU,Eversong",
[1926]="Термоштепсель,PvP,ruRU,EU,Thermaplugg",
[1927]="Гром,PvP,ruRU,EU,Grom",
[1928]="Голдринн,PvE,ruRU,EU,Goldrinn",
[1929]="Черный Шрам,PvP,ruRU,EU,Blackscar",
[201]="불타는 군단,PvE,koKR,KR,Burning Legion",
[205]="아즈샤라,PvP,koKR,KR,Azshara",
[207]="달라란,PvP,koKR,KR,Dalaran",
[210]="듀로탄,PvP,koKR,KR,Durotan",
[211]="노르간논,PvP,koKR,KR,Norgannon",
[212]="가로나,PvP,koKR,KR,Garona",
[214]="윈드러너,PvE,koKR,KR,Windrunner",
[215]="굴단,PvP,koKR,KR,Gul'dan",
[258]="알렉스트라자,PvP,koKR,KR,Alexstrasza",
[264]="말퓨리온,PvP,koKR,KR,Malfurion",
[293]="헬스크림,PvP,koKR,KR,Hellscream",
[2079]="와일드해머,PvE,koKR,KR,Wildhammer",
[2106]="렉사르,PvE,koKR,KR,Rexxar",
[2107]="하이잘,PvP,koKR,KR,Hyjal",
[2108]="데스윙,PvP,koKR,KR,Deathwing",
[2110]="세나리우스,PvP,koKR,KR,Cenarius",
[2111]="스톰레이지,PvE,koKR,KR,Stormrage",
[2116]="줄진,PvP,koKR,KR,Zul'jin",
[963]="暗影之月,PvE,zhTW,TW,Shadowmoon",
[964]="尖石,PvP,zhTW,TW,Spirestone",
[965]="雷鱗,PvP,zhTW,TW,Stormscale",
[966]="巨龍之喉,PvP,zhTW,TW,Dragonmaw",
[977]="冰霜之刺,PvP,zhTW,TW,Frostmane",
[978]="日落沼澤,PvP,zhTW,TW,Sundown Marsh",
[979]="地獄吼,PvP,zhTW,TW,Hellscream",
[980]="天空之牆,PvE,zhTW,TW,Skywall",
[982]="世界之樹,PvE,zhTW,TW,World Tree",
[985]="水晶之刺,PvP,zhTW,TW,Crystalpine Stinger",
[999]="狂熱之刃,PvP,zhTW,TW,Zealot Blade",
[1001]="冰風崗哨,PvP,zhTW,TW,Chillwind Point",
[1006]="米奈希爾,PvP,zhTW,TW,Menethil",
[1023]="屠魔山谷,PvP,zhTW,TW,Demon Fall Canyon",
[1033]="語風,PvE,zhTW,TW,Whisperwind",
[1037]="血之谷,PvP,zhTW,TW,Bleeding Hollow",
[1038]="亞雷戈斯,PvE,zhTW,TW,Arygos",
[1043]="夜空之歌,PvP,zhTW,TW,Nightsong",
[1046]="聖光之願,PvE,zhTW,TW,Light's Hope",
[1048]="銀翼要塞,PvP,zhTW,TW,Silverwing Hold",
[1049]="憤怒使者,PvP,zhTW,TW,Wrathbringer",
[1054]="阿薩斯,PvP,zhTW,TW,Arthas",
[1056]="眾星之子,PvE,zhTW,TW,Quel'dorei",
[1057]="寒冰皇冠,PvP,zhTW,TW,Icecrown",
[2075]="雲蛟衛,PvE,zhTW,TW,Order of the Cloud Serpent",
EOF;

    $validRegions = ['US','EU','TW','KR'];
    $tr = [];

    $luaLines = explode("\n", $lua);
    foreach ($luaLines as $luaLine) {
        if (!preg_match('/\[(\d+)\]\s*=\s*"([^"]+)",/', $luaLine, $res)) {
            echo "Could not parse: \"$luaLine\"\n";
            continue;
        }

        $guid = $res[1];
        $quoted = $res[2];
        $name = '';
        $region = '';

        $quotedParts = explode(',', $quoted);
        if (count($quotedParts) > 1) {
            $name = $quotedParts[0];
            for ($x = 1; $x < count($quotedParts); $x++) {
                if (in_array($quotedParts[$x], $validRegions)) {
                    $region = $quotedParts[$x];
                    break;
                }
            }
        }
        $name = trim($name);

        if ($name == '') {
            echo "Could not find name: \"$luaLine\"\n";
            continue;
        }

        if ($region == '') {
            echo "Could not find region: \"$luaLine\"\n";
            continue;
        }

        $tr[$guid] = [$region];
        $tr[$guid][] = mb_strtolower($name);
        if ($region != 'US' && isset($quotedParts[4])) {
            $tr[$guid][] = mb_strtolower($quotedParts[4]);
        }
    }

    return $tr;
}

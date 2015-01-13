--[[

WoW still doesn't have a decent function to find on which region/realmset we're playing..

See:
https://github.com/Phanx/LibRealmInfo
http://www.wowinterface.com/forums/showthread.php?t=48877

]]

local realmIDs = {
    [1136] = 'US',
    [1284] = 'US',
    [1129] = 'US',
    [106] = 'US',
    [1137] = 'US',
    [1070] = 'US',
    [52] = 'US',
    [1282] = 'US',
    [1293] = 'US',
    [3722] = 'US',
    [1276] = 'US',
    [1264] = 'US',
    [1363] = 'US',
    [1346] = 'US',
    [1288] = 'US',
    [1165] = 'US',
    [56] = 'US',
    [1566] = 'US',
    [75] = 'US',
    [69] = 'US',
    [1297] = 'US',
    [1555] = 'US',
    [77] = 'US',
    [121] = 'US',
    [3209] = 'US',
    [1128] = 'US',
    [1549] = 'US',
    [1190] = 'US',
    [1075] = 'US',
    [3723] = 'US',
    [1280] = 'US',
    [54] = 'US',
    [1168] = 'US',
    [10] = 'US',
    [1347] = 'US',
    [1296] = 'US',
    [1564] = 'US',
    [1353] = 'US',
    [73] = 'US',
    [1558] = 'US',
    [64] = 'US',
    [119] = 'US',
    [83] = 'US',
    [1371] = 'US',
    [112] = 'US',
    [117] = 'US',
    [91] = 'US',
    [102] = 'US',
    [3721] = 'US',
    [1361] = 'US',
    [88] = 'US',
    [2] = 'US',
    [1067] = 'US',
    [1138] = 'US',
    [1556] = 'US',
    [107] = 'US',
    [109] = 'US',
    [66] = 'US',
    [1278] = 'US',
    [157] = 'US',
    [120] = 'US',
    [1351] = 'US',
    [3735] = 'US',
    [1582] = 'US',
    [15] = 'US',
    [1286] = 'US',
    [1271] = 'US',
    [79] = 'US',
    [81] = 'US',
    [154] = 'US',
    [13] = 'US',
    [115] = 'US',
    [114] = 'US',
    [84] = 'US',
    [1362] = 'US',
    [1140] = 'US',
    [1139] = 'US',
    [1425] = 'US',
    [3733] = 'US',
    [1377] = 'US',
    [111] = 'US',
    [63] = 'US',
    [1258] = 'US',
    [100] = 'US',
    [1342] = 'US',
    [47] = 'US',
    [123] = 'US',
    [67] = 'US',
    [162] = 'US',
    [96] = 'US',
    [93] = 'US',
    [1277] = 'US',
    [1565] = 'US',
    [1370] = 'US',
    [118] = 'US',
    [1345] = 'US',
    [127] = 'US',
    [1576] = 'US',
    [128] = 'US',
    [3725] = 'US',
    [7] = 'US',
    [1581] = 'US',
    [3234] = 'US',
    [1141] = 'US',
    [51] = 'US',
    [1373] = 'US',
    [1578] = 'US',
    [97] = 'US',
    [1287] = 'US',
    [3207] = 'US',
    [92] = 'US',
    [80] = 'US',
    [158] = 'US',
    [1579] = 'US',
    [1068] = 'US',
    [3737] = 'US',
    [129] = 'US',
    [1142] = 'US',
    [1266] = 'US',
    [53] = 'US',
    [1368] = 'US',
    [6] = 'US',
    [14] = 'US',
    [57] = 'US',
    [3661] = 'US',
    [3675] = 'US',
    [3676] = 'US',
    [3677] = 'US',
    [3678] = 'US',
    [3683] = 'US',
    [3684] = 'US',
    [3685] = 'US',
    [3693] = 'US',
    [3694] = 'US',
    [3729] = 'US',
    [3728] = 'US',
    [1291] = 'US',
    [3736] = 'US',
    [1069] = 'US',
    [155] = 'US',
    [98] = 'US',
    [16] = 'US',
    [65] = 'US',
    [1143] = 'US',
    [3726] = 'US',
    [9] = 'US',
    [4] = 'US',
    [1071] = 'US',
    [1146] = 'US',
    [1349] = 'US',
    [1147] = 'US',
    [101] = 'US',
    [1295] = 'US',
    [1] = 'US',
    [95] = 'US',
    [1130] = 'US',
    [99] = 'US',
    [68] = 'US',
    [1173] = 'US',
    [163] = 'US',
    [78] = 'US',
    [1357] = 'US',
    [59] = 'US',
    [1132] = 'US',
    [1148] = 'US',
    [104] = 'US',
    [70] = 'US',
    [62] = 'US',
    [1350] = 'US',
    [1374] = 'US',
    [1365] = 'US',
    [153] = 'US',
    [1145] = 'US',
    [1182] = 'US',
    [3734] = 'US',
    [89] = 'US',
    [1169] = 'US',
    [1367] = 'US',
    [1131] = 'US',
    [3208] = 'US',
    [8] = 'US',
    [1375] = 'US',
    [1359] = 'US',
    [1262] = 'US',
    [1285] = 'US',
    [122] = 'US',
    [5] = 'US',
    [1428] = 'US',
    [1372] = 'US',
    [1427] = 'US',
    [1072] = 'US',
    [1352] = 'US',
    [1151] = 'US',
    [1358] = 'US',
    [151] = 'US',
    [76] = 'US',
    [3738] = 'US',
    [126] = 'US',
    [1267] = 'US',
    [1185] = 'US',
    [1290] = 'US',
    [125] = 'US',
    [94] = 'US',
    [85] = 'US',
    [1364] = 'US',
    [1557] = 'US',
    [72] = 'US',
    [1354] = 'US',
    [12] = 'US',
    [86] = 'US',
    [1356] = 'US',
    [74] = 'US',
    [131] = 'US',
    [130] = 'US',
    [82] = 'US',
    [124] = 'US',
    [160] = 'US',
    [1260] = 'US',
    [108] = 'US',
    [60] = 'US',
    [58] = 'US',
    [110] = 'US',
    [113] = 'US',
    [1292] = 'US',
    [90] = 'US',
    [1563] = 'US',
    [3724] = 'US',
    [1344] = 'US',
    [1570] = 'US',
    [1559] = 'US',
    [1289] = 'US',
    [1171] = 'US',
    [1154] = 'US',
    [1263] = 'US',
    [105] = 'US',
    [103] = 'US',
    [11] = 'US',
    [3210] = 'US',
    [1360] = 'US',
    [1175] = 'US',
    [1265] = 'US',
    [164] = 'US',
    [1283] = 'US',
    [1426] = 'US',
    [116] = 'US',
    [1294] = 'US',
    [156] = 'US',
    [3] = 'US',
    [1348] = 'US',
    [1184] = 'US',
    [1567] = 'US',
    [71] = 'US',
    [55] = 'US',
    [159] = 'US',
    [87] = 'US',
    [1355] = 'US',
    [1369] = 'US',
    [1174] = 'US',
    [1270] = 'US',
    [1268] = 'US',
    [1572] = 'US',
    [61] = 'US',
    [1259] = 'US',

    [577] = 'EU',
    [1312] = 'EU',
    [518] = 'EU',
    [1413] = 'EU',
    [500] = 'EU',
    [1093] = 'EU',
    [519] = 'EU',
    [562] = 'EU',
    [563] = 'EU',
    [1391] = 'EU',
    [601] = 'EU',
    [1330] = 'EU',
    [1394] = 'EU',
    [1104] = 'EU',
    [564] = 'EU',
    [608] = 'EU',
    [512] = 'EU',
    [1334] = 'EU',
    [501] = 'EU',
    [539] = 'EU',
    [1404] = 'EU',
    [536] = 'EU',
    [578] = 'EU',
    [1406] = 'EU',
    [1923] = 'EU',
    [502] = 'EU',
    [1597] = 'EU',
    [503] = 'EU',
    [579] = 'EU',
    [1922] = 'EU',
    [1417] = 'EU',
    [565] = 'EU',
    [607] = 'EU',
    [566] = 'EU',
    [580] = 'EU',
    [581] = 'EU',
    [1929] = 'EU',
    [1416] = 'EU',
    [521] = 'EU',
    [630] = 'EU',
    [504] = 'EU',
    [522] = 'EU',
    [1613] = 'EU',
    [1924] = 'EU',
    [1625] = 'EU',
    [1299] = 'EU',
    [1393] = 'EU',
    [1081] = 'EU',
    [523] = 'EU',
    [524] = 'EU',
    [1392] = 'EU',
    [1381] = 'EU',
    [1315] = 'EU',
    [3391] = 'EU',
    [1307] = 'EU',
    [1620] = 'EU',
    [545] = 'EU',
    [1083] = 'EU',
    [1395] = 'EU',
    [1127] = 'EU',
    [644] = 'EU',
    [525] = 'EU',
    [1337] = 'EU',
    [526] = 'EU',
    [538] = 'EU',
    [1321] = 'EU',
    [1317] = 'EU',
    [631] = 'EU',
    [1389] = 'EU',
    [1619] = 'EU',
    [614] = 'EU',
    [1605] = 'EU',
    [1617] = 'EU',
    [527] = 'EU',
    [1609] = 'EU',
    [635] = 'EU',
    [1084] = 'EU',
    [1327] = 'EU',
    [617] = 'EU',
    [1326] = 'EU',
    [582] = 'EU',
    [531] = 'EU',
    [1618] = 'EU',
    [1121] = 'EU',
    [1333] = 'EU',
    [576] = 'EU',
    [1119] = 'EU',
    [1118] = 'EU',
    [505] = 'EU',
    [506] = 'EU',
    [507] = 'EU',
    [528] = 'EU',
    [1092] = 'EU',
    [641] = 'EU',
    [1378] = 'EU',
    [600] = 'EU',
    [529] = 'EU',
    [535] = 'EU',
    [561] = 'EU',
    [1612] = 'EU',
    [1123] = 'EU',
    [1336] = 'EU',
    [540] = 'EU',
    [508] = 'EU',
    [1091] = 'EU',
    [1310] = 'EU',
    [583] = 'EU',
    [1925] = 'EU',
    [1087] = 'EU',
    [1385] = 'EU',
    [1611] = 'EU',
    [1623] = 'EU',
    [516] = 'EU',
    [1300] = 'EU',
    [584] = 'EU',
    [632] = 'EU',
    [585] = 'EU',
    [1614] = 'EU',
    [1390] = 'EU',
    [509] = 'EU',
    [1401] = 'EU',
    [606] = 'EU',
    [1588] = 'EU',
    [567] = 'EU',
    [1403] = 'EU',
    [1928] = 'EU',
    [1602] = 'EU',
    [586] = 'EU',
    [1610] = 'EU',
    [1303] = 'EU',
    [1927] = 'EU',
    [1325] = 'EU',
    [587] = 'EU',
    [646] = 'EU',
    [638] = 'EU',
    [1587] = 'EU',
    [619] = 'EU',
    [1615] = 'EU',
    [542] = 'EU',
    [541] = 'EU',
    [3656] = 'EU',
    [3657] = 'EU',
    [3660] = 'EU',
    [3666] = 'EU',
    [3674] = 'EU',
    [3679] = 'EU',
    [3680] = 'EU',
    [3681] = 'EU',
    [3682] = 'EU',
    [3686] = 'EU',
    [3687] = 'EU',
    [3690] = 'EU',
    [3691] = 'EU',
    [3692] = 'EU',
    [3696] = 'EU',
    [3702] = 'EU',
    [3703] = 'EU',
    [3713] = 'EU',
    [3714] = 'EU',
    [1304] = 'EU',
    [543] = 'EU',
    [1596] = 'EU',
    [568] = 'EU',
    [1305] = 'EU',
    [588] = 'EU',
    [1080] = 'EU',
    [640] = 'EU',
    [569] = 'EU',
    [589] = 'EU',
    [1311] = 'EU',
    [537] = 'EU',
    [633] = 'EU',
    [616] = 'EU',
    [1332] = 'EU',
    [1082] = 'EU',
    [613] = 'EU',
    [1086] = 'EU',
    [621] = 'EU',
    [1626] = 'EU',
    [647] = 'EU',
    [1603] = 'EU',
    [1388] = 'EU',
    [637] = 'EU',
    [1409] = 'EU',
    [1387] = 'EU',
    [570] = 'EU',
    [571] = 'EU',
    [622] = 'EU',
    [590] = 'EU',
    [572] = 'EU',
    [1324] = 'EU',
    [1098] = 'EU',
    [591] = 'EU',
    [1621] = 'EU',
    [1089] = 'EU',
    [517] = 'EU',
    [1402] = 'EU',
    [1386] = 'EU',
    [1085] = 'EU',
    [1319] = 'EU',
    [1329] = 'EU',
    [1589] = 'EU',
    [594] = 'EU',
    [1624] = 'EU',
    [1105] = 'EU',
    [612] = 'EU',
    [1316] = 'EU',
    [624] = 'EU',
    [544] = 'EU',
    [611] = 'EU',
    [1607] = 'EU',
    [618] = 'EU',
    [1408] = 'EU',
    [574] = 'EU',
    [610] = 'EU',
    [1301] = 'EU',
    [575] = 'EU',
    [1309] = 'EU',
    [593] = 'EU',
    [623] = 'EU',
    [626] = 'EU',
    [1322] = 'EU',
    [642] = 'EU',
    [554] = 'EU',
    [1308] = 'EU',
    [1616] = 'EU',
    [1099] = 'EU',
    [547] = 'EU',
    [1382] = 'EU',
    [546] = 'EU',
    [1314] = 'EU',
    [1096] = 'EU',
    [602] = 'EU',
    [2074] = 'EU',
    [548] = 'EU',
    [1598] = 'EU',
    [556] = 'EU',
    [1608] = 'EU',
    [1383] = 'EU',
    [549] = 'EU',
    [533] = 'EU',
    [557] = 'EU',
    [1604] = 'EU',
    [558] = 'EU',
    [1606] = 'EU',
    [1117] = 'EU',
    [550] = 'EU',
    [559] = 'EU',
    [560] = 'EU',
    [511] = 'EU',
    [1331] = 'EU',
    [628] = 'EU',
    [1320] = 'EU',
    [1090] = 'EU',
    [1306] = 'EU',
    [1407] = 'EU',
    [1622] = 'EU',
    [551] = 'EU',
    [1415] = 'EU',
    [615] = 'EU',
    [627] = 'EU',
    [1595] = 'EU',
    [636] = 'EU',
    [605] = 'EU',
    [1926] = 'EU',
    [604] = 'EU',
    [643] = 'EU',
    [552] = 'EU',
    [1106] = 'EU',
    [1328] = 'EU',
    [1405] = 'EU',
    [1088] = 'EU',
    [553] = 'EU',
    [513] = 'EU',
    [625] = 'EU',
    [1384] = 'EU',
    [1122] = 'EU',
    [1323] = 'EU',
    [1380] = 'EU',
    [1400] = 'EU',
    [645] = 'EU',
    [629] = 'EU',
    [1318] = 'EU',
    [1298] = 'EU',
    [510] = 'EU',
    [1313] = 'EU',
    [2073] = 'EU',
    [609] = 'EU',
    [639] = 'EU',
    [1097] = 'EU',
    [1335] = 'EU',
    [515] = 'EU',
    [592] = 'EU',
    [1379] = 'EU',
    [573] = 'EU',
}

local addonName, addonTable = ...

addonTable.GetRegion = function()
    local guid = UnitGUID("player")
    if guid then
        local realmId = tonumber(strmatch(guid, "^Player%-(%d+)"))

        return realmIDs[realmId] or addonTable.region
    end
    return addonTable.region
end

addonTable.region = addonTable.GetRegion() or string.upper(GetCVar("portal") or "US")

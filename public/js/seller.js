var TUJ_Seller = function ()
{
    var params;
    var lastResults = [];

    this.load = function (inParams)
    {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        var qs = {
            realm: params.realm,
            seller: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++) {
            if (lastResults[x].hash == hash) {
                SellerResult(false, lastResults[x].data);
                return;
            }
        }

        var sellerPage = $('#seller-page')[0];
        if (!sellerPage) {
            sellerPage = libtuj.ce();
            sellerPage.id = 'seller-page';
            sellerPage.className = 'page';
            $('#main').append(sellerPage);
        }

        $('#progress-page').show();

        $.ajax({
            data: qs,
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                }
                else {
                    SellerResult(hash, d);
                }
            },
            error: function (xhr, stat, er)
            {
                if ((xhr.status == 503) && xhr.hasOwnProperty('responseJSON') && xhr.responseJSON && xhr.responseJSON.hasOwnProperty('maintenance')) {
                    tuj.APIMaintenance(xhr.responseJSON.maintenance);
                } else {
                    alert('Error fetching page data: ' + stat + ' ' + er);
                }
            },
            complete: function ()
            {
                $('#progress-page').hide();
            },
            url: 'api/seller.php'
        });
    }

    function SellerResult(hash, dta)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var sellerPage = $('#seller-page');
        sellerPage.empty();
        sellerPage.show();

        if (!dta.stats) {
            $('#page-title').empty().append(document.createTextNode(tuj.lang.seller + ': ' + params.id));
            tuj.SetTitle(tuj.lang.seller + ': ' + params.id);

            var h2 = libtuj.ce('h2');
            sellerPage.append(h2);
            h2.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.notFound, tuj.lang.seller + ' ' + params.id)));

            return;
        }

        $('#page-title').empty().append(document.createTextNode(tuj.lang.seller + ': ' + dta.stats.name));
        tuj.SetTitle(tuj.lang.seller + ': ' + dta.stats.name);

        var d, cht, h;

        sellerPage.append(libtuj.Ads.Add('3896661119'));

        d = libtuj.ce();
        d.className = 'chart-section';
        h = libtuj.ce('h2');
        d.appendChild(h);
        $(h).text(tuj.lang.snapshots);
        d.appendChild(document.createTextNode(tuj.lang.snapshotsSellerDesc))
        cht = libtuj.ce();
        cht.className = 'chart history';
        d.appendChild(cht);
        sellerPage.append(d);
        SellerHistoryChart(dta, cht);

        if (dta.history.length >= 14) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.postingHeatMap);
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerPostingHeatMap(dta, cht);
        }

        if (dta.byClass && dta.byClass.length > 2) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Items By Class [PH]');
            cht = libtuj.ce();
            cht.className = 'chart byclass';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerByItemClass(dta, cht);
        }

        if (dta.auctions.length) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.currentAuctions);
            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerAuctions(dta, cht);
        }

        if (dta.petAuctions.length) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.currentPetAuctions);
            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerPetAuctions(dta, cht);
        }

        libtuj.Ads.Show();
    }

    function SellerHistoryChart(data, dest)
    {
        var hcdata = {total: [], newAuc: [], max: 0};

        for (var x = 0; x < data.history.length; x++) {
            hcdata.total.push([data.history[x].snapshot * 1000, data.history[x].total]);
            hcdata.newAuc.push([data.history[x].snapshot * 1000, data.history[x]['new']]);
            if (data.history[x].total > hcdata.max) {
                hcdata.max = data.history[x].total;
            }
        }

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts({
            chart: {
                zoomType: 'x',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    tuj.lang.zoomClickDrag :
                    tuj.lang.zoomPinch,
                style: {
                    color: tujConstants.siteColors[tuj.colorTheme].text
                }
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 3600000, // four hours
                title: {
                    text: null
                },
                labels: {
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].text
                    }
                }
            },
            yAxis: [
                {
                    title: {
                        text: tuj.lang.numberOfAuctions,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].bluePrice
                        }
                    },
                    labels: {
                        enabled: true,
                        formatter: function ()
                        {
                            return '' + libtuj.FormatQuantity(this.value, true);
                        },
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].text
                        }
                    },
                    min: 0,
                    max: hcdata.max
                }
            ],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function ()
                {
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %e %Y, %l:%M%P', this.x) + '</b>';
                    tr += '<br><span style="color: #000099">' + tuj.lang.total + ': ' + libtuj.FormatQuantity(this.points[0].y, true) + '</span>';
                    tr += '<br><span style="color: #990000">' + tuj.lang['new'] + ': ' + libtuj.FormatQuantity(this.points[1].y, true) + '</span>';
                    return tr;
                }
            },
            plotOptions: {
                series: {
                    lineWidth: 2,
                    marker: {
                        enabled: false,
                        radius: 1,
                        states: {
                            hover: {
                                enabled: true
                            }
                        }
                    },
                    states: {
                        hover: {
                            lineWidth: 2
                        }
                    }
                }
            },
            series: [
                {
                    type: 'area',
                    name: tuj.lang.total,
                    color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    fillColor: tujConstants.siteColors[tuj.colorTheme].bluePriceFill,
                    data: hcdata.total
                },
                {
                    type: 'line',
                    name: tuj.lang['new'],
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                    data: hcdata.newAuc
                }
            ]
        });
    }

    function SellerPostingHeatMap(data, dest)
    {
        var hcdata = {minVal: undefined, maxVal: 0, days: {}, heat: [], categories: {
            x: tuj.lang.heatMapHours,
            y: tuj.lang.heatMapDays
        }};

        var CalcAvg = function (a)
        {
            if (a.length == 0) {
                return null;
            }
            var s = 0;
            for (var x = 0; x < a.length; x++) {
                s += a[x];
            }
            return s / a.length;
        }

        var d, wkdy, hr;
        for (wkdy = 0; wkdy < hcdata.categories.y.length; wkdy++) {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr < hcdata.categories.x.length; hr++) {
                hcdata.days[wkdy][hr] = [];
            }
        }

        for (var x = 0; x < data.history.length; x++) {
            var d = new Date(data.history[x].snapshot * 1000);
            wkdy = 6 - d.getDay();
            hr = Math.floor(d.getHours() * hcdata.categories.x.length / 24);
            hcdata.days[wkdy][hr].push(data.history[x]['new']);
        }

        var p;
        for (wkdy = 0; wkdy < hcdata.categories.y.length; wkdy++) {
            for (hr = 0; hr < hcdata.categories.x.length; hr++) {
                if (hcdata.days[wkdy][hr].length == 0) {
                    p = 0;
                }
                else {
                    p = Math.round(CalcAvg(hcdata.days[wkdy][hr]));
                }

                hcdata.heat.push([hr, wkdy, p]);
                hcdata.minVal = (typeof hcdata.minVal == 'undefined' || hcdata.minVal > p) ? p : hcdata.minVal;
                hcdata.maxVal = hcdata.maxVal < p ? p : hcdata.maxVal;
            }
        }

        $(dest).highcharts({

            chart: {
                type: 'heatmap',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },

            title: {
                text: null
            },

            xAxis: {
                categories: hcdata.categories.x,
                labels: {
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].text
                    }
                }
            },

            yAxis: {
                categories: hcdata.categories.y,
                title: null,
                labels: {
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].text
                    }
                }
            },

            colorAxis: {
                min: hcdata.minVal,
                max: hcdata.maxVal,
                minColor: tujConstants.siteColors[tuj.colorTheme].background,
                maxColor: tujConstants.siteColors[tuj.colorTheme].bluePriceBackground
            },

            legend: {
                align: 'right',
                layout: 'vertical',
                margin: 0,
                verticalAlign: 'top',
                y: 25,
                symbolHeight: 320
            },

            tooltip: {
                enabled: false
            },

            series: [
                {
                    name: tuj.lang.newAuctions,
                    borderWidth: 1,
                    borderColor: tujConstants.siteColors[tuj.colorTheme].background,
                    data: hcdata.heat,
                    dataLabels: {
                        enabled: true,
                        color: tujConstants.siteColors[tuj.colorTheme].data,
                        style: {
                            textShadow: 'none',
                            HcTextStroke: null
                        },
                        formatter: function ()
                        {
                            return '' + libtuj.FormatQuantity(this.point.value, true);
                        }
                    }
                }
            ]

        });
    }

    function SellerByItemClass(data, dest)
    {
        var hcdata = {
            byClass: [],
            bySubClass: [],
            classLookup: {},
            grouped: {},
            groupOrder: [],
            totalAucs: 0,
        };

        data.byClass.sort(function(a,b) {
            return b.aucs - a.aucs;
        });

        var colorArray = Highcharts.getOptions().colors;

        for (var i = 0, row; row = data.byClass[i]; i++) {
            if (!hcdata.classLookup.hasOwnProperty(row['class'])) {
                hcdata.classLookup[row['class']] = hcdata.byClass.length;
                hcdata.byClass.push({
                    name: tuj.lang.itemClasses[row['class']],
                    y: 0,
                    color: colorArray[hcdata.byClass.length]
                });
                hcdata.grouped[row['class']] = [];
                hcdata.groupOrder.push(row['class']);
            }
            hcdata.byClass[hcdata.classLookup[row['class']]].y += row.aucs;

            hcdata.grouped[row['class']].push({
                name: tuj.lang.itemClasses[row['class']] + ': ' + tuj.lang.itemSubClasses['' + row['class'] + '-' + row.subclass],
                y: row.aucs,
                color: Highcharts.Color(hcdata.byClass[hcdata.classLookup[row['class']]].color).brighten(0.1).get()
            });

            hcdata.totalAucs += row.aucs;
        }

        for (var i = 0; i < hcdata.groupOrder.length; i++) {
            hcdata.bySubClass = hcdata.bySubClass.concat(hcdata.grouped[hcdata.groupOrder[i]]);
        }

        $(dest).highcharts({
            chart: {
                type: 'pie',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },

            title: {
                text: null
            },

            yAxis: {
                title: null,
            },

            tooltip: {
                formatter: function () {
                    var tr = '<b>' + this.point.name + '</b>';
                    tr += '<br>' + this.point.y + ' (' + Math.round(this.point.y / hcdata.totalAucs * 100) + '%)';
                    return tr;
                }
            },

            plotOptions: {
                pie: {
                    shadow: false,
                    center: ['50%','100%'],
                    startAngle: -90,
                    endAngle: 90,
                }
            },

            series: [{
                data: hcdata.byClass,
                size: '125%',
                dataLabels: {
                    formatter: function() {
                        return this.y > hcdata.totalAucs * 0.02 ? this.point.name : null
                    }
                }
            }, {
                data: hcdata.bySubClass,
                size: '175%',
                innerSize: '125%',
                dataLabels: {
                    enabled: false,
                }
            }]
        });
    }

    function SellerAuctions(data, dest)
    {
        var t, tr, td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quantity';
        $(td).text(tuj.lang.quantity);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'name';
        td.colSpan = 2;
        $(td).text(tuj.lang.item);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.bidEach);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.buyoutEach);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quantity';
        $(td).text(tuj.lang.cheaper);

        data.auctions.sort(function (a, b)
        {
            return tujConstants.itemClassOrder[a['class']] - tujConstants.itemClassOrder[b['class']] ||
                (a['name_' + tuj.locale] ? 0 : -1) ||
                (b['name_' + tuj.locale] ? 0 : 1) ||
                a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                Math.floor(a.buy / a.quantity) - Math.floor(b.buy / b.quantity) ||
                Math.floor(a.bid / a.quantity) - Math.floor(b.bid / b.quantity) ||
                a.quantity - b.quantity;
        });

        var s, a, stackable, i;
        for (var x = 0, auc; auc = data.auctions[x]; x++) {
            stackable = auc.stacksize > 1;

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quantity';
            td.appendChild(libtuj.FormatQuantity(auc.quantity));

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = libtuj.IconURL(auc.icon, 'medium');

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'name';
            a = libtuj.ce('a');
            a.rel = 'item=' + auc.item + (auc.rand ? '&rand=' + auc.rand : '') + (auc.bonuses ? '&bonus=' + auc.bonuses : '') + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
            a.href = tuj.BuildHash({page: 'item', id: auc.item + (auc.bonusurl ? ('.'+auc.bonusurl).replace(':','.') : '')});
            td.appendChild(a);
            $(a).text('[' + auc['name_' + tuj.locale] + (auc['bonusname_' + tuj.locale] ? ' ' + auc['bonusname_' + tuj.locale].substr(0, auc['bonusname_' + tuj.locale].indexOf('|') >= 0 ? auc['bonusname_' + tuj.locale].indexOf('|') : auc['bonusname_' + tuj.locale].length) : '') + (auc['randname_' + tuj.locale] ? ' ' + auc['randname_' + tuj.locale] : '') + ']' + (auc['bonustag_' + tuj.locale] ? ' ' : ''));
            if (auc['bonustag_' + tuj.locale]) {
                var tagspan = libtuj.ce('span');
                tagspan.className = 'nowrap';
                var bonusTag = auc['bonustag_' + tuj.locale];
                if (!isNaN(bonusTag)) {
                    bonusTag = tuj.lang.level + ' ' + (auc.level + parseInt(bonusTag, 10));
                }
                $(tagspan).text(bonusTag);
                a.appendChild(tagspan);
            }

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.bid / auc.quantity);
            if (stackable && auc.quantity > 1) {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatFullPrice(auc.bid, true) + ' ' + tuj.lang.total;
                a.appendChild(s);
            }
            else {
                a = s;
            }
            td.appendChild(a);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.buy / auc.quantity);
            if (stackable && auc.quantity > 1 && auc.buy) {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatFullPrice(auc.buy, true) + ' ' + tuj.lang.total;
                a.appendChild(s);
            }
            else {
                if (!auc.buy) {
                    a = libtuj.ce('span');
                }
                else {
                    a = s;
                }
            }
            if (a) {
                td.appendChild(a);
            }

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quantity';
            if (auc.cheaper) {
                td.appendChild(libtuj.FormatQuantity(auc.cheaper));
            }
        }

        dest.appendChild(t);
    }

    function SellerPetAuctions(data, dest)
    {
        var t, tr, td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'name';
        td.colSpan = 2;
        $(td).text(tuj.lang.species);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'breed';
        $(td).text(tuj.lang.breed);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quality';
        $(td).text(tuj.lang.quality);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'level';
        $(td).text(tuj.lang.level);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.bidEach);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.buyoutEach);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quantity';
        $(td).text(tuj.lang.cheaper);

        data.petAuctions.sort(function (a, b)
        {
            return a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                tuj.lang.breedsLookup[a.breed].localeCompare(tuj.lang.breedsLookup[b.breed]) ||
                a.quality - b.quality ||
                a.buy - b.buy ||
                a.bid - b.bid;
        });

        var s, a, i;
        for (var x = 0, auc; auc = data.petAuctions[x]; x++) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = libtuj.IconURL(auc.icon, 'medium');

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'name';
            a = libtuj.ce('a');
            a.rel = 'npc=' + auc.npc + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
            a.href = tuj.BuildHash({page: 'battlepet', id: auc.species});
            td.appendChild(a);
            $(a).text('[' + auc['name_' + tuj.locale] + ']');

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'breed';
            td.appendChild(document.createTextNode(tuj.lang.breedsLookup[auc.breed]));

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quality';
            td.appendChild(document.createTextNode(tuj.lang.qualities[auc.quality]));

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'level';
            td.appendChild(document.createTextNode(auc.level));

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.bid / auc.quantity);
            td.appendChild(s);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.buy / auc.quantity);
            if (!auc.buy) {
                a = libtuj.ce('span');
            }
            else {
                a = s;
            }
            if (a) {
                td.appendChild(a);
            }

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quantity';
            if (auc.cheaper) {
                td.appendChild(libtuj.FormatQuantity(auc.cheaper));
            }
        }

        dest.appendChild(t);
    }

    this.load(tuj.params);
}

tuj.page_seller = new TUJ_Seller();

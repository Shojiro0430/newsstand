
var TUJ_Item = function()
{
    var params;
    var lastResults = [];

    this.load = function(inParams)
    {
        params = {};
        for (var p in inParams)
            if (inParams.hasOwnProperty(p))
                params[p] = inParams[p];

        var qs = {
            house: tuj.realms[params.realm].house * tuj.validFactions[params.faction],
            item: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++)
            if (lastResults[x].hash == hash)
            {
                ItemResult(false, lastResults[x].data);
                return;
            }

        var itemPage = $('#item-page')[0];
        if (!itemPage)
        {
            itemPage = libtuj.ce();
            itemPage.id = 'item-page';
            itemPage.className = 'page';
            $('#main').append(itemPage);
        }

        $('#progress-page').show();

        $.ajax({
            data: qs,
            success: function(d) {
                if (d.captcha)
                    tuj.AskCaptcha(d.captcha);
                else
                    ItemResult(hash, d);
            },
            complete: function() {
                $('#progress-page').hide();
            },
            url: 'api/item.php'
        });
    }

    function ItemResult(hash, dta)
    {
        if (hash)
        {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10)
                lastResults.shift();
        }

        var ta = libtuj.ce('a');
        ta.href = 'http://www.wowhead.com/item=' + dta.stats.id;
        ta.target = '_blank';
        ta.className = 'item'
        var timg = libtuj.ce('img');
        ta.appendChild(timg);
        timg.src = 'icon/large/' + dta.stats.icon + '.jpg';
        ta.appendChild(document.createTextNode('[' + dta.stats.name + ']'));

        $('#page-title').empty().append(ta);
        tuj.SetTitle('[' + dta.stats.name + ']');

        var itemPage = $('#item-page');
        itemPage.empty();
        itemPage.show();

        var d, cht, h;

        d = libtuj.ce();
        d.className = 'item-stats';
        itemPage.append(d);
        ItemStats(dta, d);

        if (dta.history.length >= 4)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Snapshots');
            d.appendChild(document.createTextNode('Here is the available quantity and market price of the item for every auction house snapshot seen recently.'))
            cht = libtuj.ce();
            cht.className = 'chart history';
            d.appendChild(cht);
            itemPage.append(d);
            ItemHistoryChart(dta, cht);
        }

        if (dta.monthly.length >= 7)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Daily Summary');
            d.appendChild(document.createTextNode('Here is the maximum available quantity, and the market price at that time, for the item each day.'))
            cht = libtuj.ce();
            cht.className = 'chart monthly';
            d.appendChild(cht);
            itemPage.append(d);
            ItemMonthlyChart(dta, cht);
        }

        if (dta.daily.length >= 7)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Daily Details');
            d.appendChild(document.createTextNode('This chart is similar to the Daily Summary, but includes the "OHLC" market prices for the item each day, along with the minimum, average, and maximum available quantity.'))
            cht = libtuj.ce();
            cht.className = 'chart daily';
            d.appendChild(cht)
            itemPage.append(d);
            ItemDailyChart(dta, cht);
        }

        if (dta.history.length >= 14)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Pricing Heat Map');
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            itemPage.append(d);
            ItemPriceHeatMap(dta, cht);

            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Quantity Heat Map');
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            itemPage.append(d);
            ItemQuantityHeatMap(dta, cht);
        }

        if (dta.globalnow.length > 2)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Current Regional Prices');
            cht = libtuj.ce();
            cht.className = 'chart columns';
            d.appendChild(cht);
            itemPage.append(d);
            ItemGlobalNowColumns(dta, cht);
        }

        if (dta.auctions.length)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Current Auctions');
            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            itemPage.append(d);
            ItemAuctions(dta, cht);
        }
    }

    function ItemStats(data, dest)
    {
        var t, tr, td, abbr;

        var stack = data.stats.stacksize > 1 ? data.stats.stacksize : 0;
        var spacerColSpan = stack ? 3 : 2;

        t = libtuj.ce('table');
        dest.appendChild(t);

        if (stack)
        {
            t.className = 'with-stack';
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'stack-header';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('One'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.style.whiteSpace = 'nowrap';
            td.appendChild(document.createTextNode('Stack of '+stack));
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'available';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Available Quantity'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatQuantity(data.stats.quantity));
        if (stack)
        {
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatQuantity(Math.floor(data.stats.quantity/stack)));
        }

        if (data.stats.quantity == 0)
        {
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'last-seen';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('Last Seen'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.colSpan = stack ? 2 : 1;
            td.appendChild(libtuj.FormatDate(data.stats.lastseen));
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'spacer';
        td = libtuj.ce('td');
        td.colSpan = spacerColSpan;
        tr.appendChild(td);

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'current-price';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Current Price'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatPrice(data.stats.price));
        if (stack)
        {
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(data.stats.price*stack));
        }

        var prices = [], x;

        if (data.history.length > 8)
            for (x = 0; x < data.history.length; x++)
                prices.push(data.history[x].price);

        if (prices.length)
        {
            var median;
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'median-price';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('Median Price'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(median = libtuj.Median(prices)));
            if (stack)
            {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(median*stack));
            }

            var mn = libtuj.Mean(prices);
            var std = libtuj.StdDev(prices, mn);
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'mean-price';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('Mean Price'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(mn));
            if (stack)
            {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(mn*stack));
            }

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'standard-deviation';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('Standard Deviation'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            if (std / mn > 0.33)
            {
                abbr = libtuj.ce('abbr');
                abbr.title = 'Market price is highly volatile!';
                abbr.style.fontSize = '80%';
                abbr.appendChild(document.createTextNode('(!)'));
                td.appendChild(abbr);
                td.appendChild(document.createTextNode(' '));
            }
            td.appendChild(libtuj.FormatPrice(std));
            if (stack)
            {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(std*stack));
            }
        }

        if (data.globalnow.length)
        {
            var globalStats = {
                quantity: 0,
                prices: [],
                lastseen: 0
            };

            var headerPrefix = tuj.region + '-' + params.faction.substr(0,1).toUpperCase() + ' ';
            var row;
            for (x = 0; row = data.globalnow[x]; x++)
            {
                globalStats.quantity += row.quantity;
                globalStats.prices.push(row.price);
                globalStats.lastseen = (globalStats.lastseen < row.lastseen) ? row.lastseen : globalStats.lastseen;
            }

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'spacer';
            td = libtuj.ce('td');
            td.colSpan = spacerColSpan;
            tr.appendChild(td);

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'available';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(headerPrefix + 'Quantity'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatQuantity(globalStats.quantity));
            if (stack)
            {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatQuantity(Math.floor(globalStats.quantity/stack)));
            }

            var median;
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'median-price';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(headerPrefix + 'Median Price'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(median = libtuj.Median(globalStats.prices)));
            if (stack)
            {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(median*stack));
            }

            var mn = libtuj.Mean(globalStats.prices);
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'mean-price';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(headerPrefix + 'Mean Price'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(mn));
            if (stack)
            {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(mn*stack));
            }
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'spacer';
        td = libtuj.ce('td');
        td.colSpan = spacerColSpan;
        tr.appendChild(td);

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'vendor';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Sell to Vendor'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(data.stats.selltovendor ? libtuj.FormatPrice(data.stats.selltovendor) : document.createTextNode('Cannot'));
        if (stack)
        {
            if (data.stats.selltovendor)
            {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(data.stats.selltovendor*stack));
            }
            else
                td.colSpan = 2;
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'listing';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('48hr Listing Fee'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatPrice(Math.max(100, data.stats.selltovendor ? data.stats.selltovendor * 0.6 : 0)));
        if (stack)
        {
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(Math.max(100, data.stats.selltovendor ? data.stats.selltovendor * 0.6 * stack : 0)));
        }

        var ad = libtuj.ce();
        ad.className = 'ad box';
        dest.appendChild(ad);

        var ins = libtuj.ce('ins');
        ad.appendChild(ins);
        ins.className = 'adsbygoogle';
        ins.setAttribute('data-ad-client', 'ca-pub-1018837251546750');
        ins.setAttribute('data-ad-slot', '9943194718');
        (window.adsbygoogle = window.adsbygoogle || []).push({});
    }

    function ItemHistoryChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0};

        var allPrices = [];
        for (var x = 0; x < data.history.length; x++)
        {
            hcdata.price.push([data.history[x].snapshot*1000, data.history[x].price]);
            hcdata.quantity.push([data.history[x].snapshot*1000, data.history[x].quantity]);
            if (data.history[x].quantity > hcdata.quantityMaxVal)
                hcdata.quantityMaxVal = data.history[x].quantity;
            allPrices.push(data.history[x].price);
        }

        allPrices.sort(function(a,b){ return a - b; });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.priceMaxVal = q3 + (1.5 * iqr);

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts({
            chart: {
                zoomType: 'x'
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in'
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 3600000, // four hours
                title: {
                    text: null
                }
            },
            yAxis: [{
                title: {
                    text: 'Market Price',
                    style: {
                        color: '#0000FF'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatPrice(this.value, true); }
                },
                min: 0,
                max: hcdata.priceMaxVal
            }, {
                title: {
                    text: 'Quantity Available',
                    style: {
                        color: '#FF3333'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatQuantity(this.value, true); }
                },
                opposite: true,
                min: 0,
                max: hcdata.quantityMaxVal
            }],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function() {
                    var tr = '<b>'+Highcharts.dateFormat('%a %b %d, %I:%M%P', this.x)+'</b>';
                    tr += '<br><span style="color: #000099">Market Price: '+libtuj.FormatPrice(this.points[0].y, true)+'</span>';
                    tr += '<br><span style="color: #990000">Quantity: '+libtuj.FormatQuantity(this.points[1].y, true)+'</span>';
                    return tr;
                    // &lt;br/&gt;&lt;span style="color: #990000"&gt;Quantity: '+this.points[1].y+'&lt;/span&gt;<xsl:if test="itemgraphs/d[@matsprice != '']">&lt;br/&gt;&lt;span style="color: #999900"&gt;Materials Price: '+this.points[2].y.toFixed(2)+'g&lt;/span&gt;</xsl:if>';
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
            series: [{
                type: 'area',
                name: 'Market Price',
                color: '#0000FF',
                lineColor: '#0000FF',
                fillColor: '#CCCCFF',
                data: hcdata.price
            },{
                type: 'line',
                name: 'Quantity Available',
                yAxis: 1,
                color: '#FF3333',
                data: hcdata.quantity
            }]
        });
    }

    function ItemMonthlyChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0};

        var allPrices = [], dt, dtParts;
        var offset = (new Date()).getTimezoneOffset() * 60 * 1000;
        for (var x = 0; x < data.monthly.length; x++)
        {
            dtParts = data.monthly[x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1],10)-1, dtParts[2]) + offset;
            hcdata.price.push([dt, data.monthly[x].silver * 100]);
            hcdata.quantity.push([dt, data.monthly[x].quantity]);
            if (data.monthly[x].quantity > hcdata.quantityMaxVal)
                hcdata.quantityMaxVal = data.monthly[x].quantity;
            allPrices.push(data.monthly[x].silver * 100);
        }

        allPrices.sort(function(a,b){ return a - b; });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.priceMaxVal = q3 + (1.5 * iqr);

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts({
            chart: {
                zoomType: 'x'
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in'
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 24 * 3600000, // four days
                title: {
                    text: null
                }
            },
            yAxis: [{
                title: {
                    text: 'Market Price',
                    style: {
                        color: '#0000FF'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatPrice(this.value, true); }
                },
                min: 0,
                max: hcdata.priceMaxVal
            }, {
                title: {
                    text: 'Quantity Available',
                    style: {
                        color: '#FF3333'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatQuantity(this.value, true); }
                },
                opposite: true,
                min: 0,
                max: hcdata.quantityMaxVal
            }],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function() {
                    var tr = '<b>'+Highcharts.dateFormat('%a %b %d', this.x)+'</b>';
                    tr += '<br><span style="color: #000099">Market Price: '+libtuj.FormatPrice(this.points[0].y, true)+'</span>';
                    tr += '<br><span style="color: #990000">Quantity: '+libtuj.FormatQuantity(this.points[1].y, true)+'</span>';
                    return tr;
                    // &lt;br/&gt;&lt;span style="color: #990000"&gt;Quantity: '+this.points[1].y+'&lt;/span&gt;<xsl:if test="itemgraphs/d[@matsprice != '']">&lt;br/&gt;&lt;span style="color: #999900"&gt;Materials Price: '+this.points[2].y.toFixed(2)+'g&lt;/span&gt;</xsl:if>';
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
            series: [{
                type: 'area',
                name: 'Market Price',
                color: '#0000FF',
                lineColor: '#0000FF',
                fillColor: '#CCCCFF',
                data: hcdata.price
            },{
                type: 'line',
                name: 'Quantity Available',
                yAxis: 1,
                color: '#FF3333',
                data: hcdata.quantity
            }]
        });
    }

    function ItemDailyChart(data, dest)
    {
        var hcdata = {
            ohlc: [],
            ohlcMaxVal: 0,
            price: [],
            quantity: [],
            quantityRange: [],
            quantityMaxVal: 0
        };

        var allPrices = [], dt, dtParts;
        var offset = (new Date()).getTimezoneOffset() * 60 * 1000;
        for (var x = 0; x < data.daily.length; x++)
        {
            dtParts = data.daily[x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1],10)-1, dtParts[2]) + offset;

            hcdata.ohlc.push([dt,
                data.daily[x].silverstart * 100,
                data.daily[x].silvermax * 100,
                data.daily[x].silvermin * 100,
                data.daily[x].silverend * 100
            ]);
            allPrices.push(data.daily[x].silvermax * 100);

            hcdata.price.push([dt, data.daily[x].silveravg * 100]);

            hcdata.quantity.push([dt, data.daily[x].quantityavg]);
            hcdata.quantityRange.push([dt, data.daily[x].quantitymin, data.daily[x].quantitymax]);
            if (data.daily[x].quantityavg > hcdata.quantityMaxVal)
                hcdata.quantityMaxVal = data.daily[x].quantityavg;
        }

        allPrices.sort(function(a,b){ return a - b; });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.ohlcMaxVal = q3 + (1.5 * iqr);

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts('StockChart', {
            chart: {
                zoomType: 'x'
            },
            rangeSelector: {
                enabled: false
            },
            navigator: {
                enabled: false
            },
            scrollbar: {
                enabled: false
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in'
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 24 * 3600000, // four days
                title: {
                    text: null
                }
            },
            yAxis: [{
                title: {
                    text: 'Market Price',
                    style: {
                        color: '#0000FF'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatPrice(this.value, true); },
                },
                height: '60%',
                min: 0,
                max: hcdata.ohlcMaxVal
            }, {
                title: {
                    text: 'Quantity Available',
                    style: {
                        color: '#FF3333'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatQuantity(this.value, true); }
                },
                top: '65%',
                height: '35%',
                min: 0,
                max: hcdata.quantityMaxVal,
                offset: -25
            }],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function() {
                    var tr = '<b>'+Highcharts.dateFormat('%a %b %d', this.x)+'</b>';
                    tr += '<br><table class="highcharts-tuj-tooltip" style="color: #000099;" cellspacing="0" cellpadding="0">';
                    tr += '<tr><td>Open:</td><td align="right">'+libtuj.FormatPrice(this.points[0].point.open, true)+'</td></tr>';
                    tr += '<tr><td>High:</td><td align="right">'+libtuj.FormatPrice(this.points[0].point.high, true)+'</td></tr>';
                    tr += '<tr style="color: #009900"><td>Avg:</td><td align="right">'+libtuj.FormatPrice(this.points[3].y, true)+'</td></tr>';
                    tr += '<tr><td>Low:</td><td align="right">'+libtuj.FormatPrice(this.points[0].point.low, true)+'</td></tr>';
                    tr += '<tr><td>Close:</td><td align="right">'+libtuj.FormatPrice(this.points[0].point.close, true)+'</td></tr>';
                    tr += '</table>';
                    tr += '<br><table class="highcharts-tuj-tooltip" style="color: #FF3333;" cellspacing="0" cellpadding="0">';
                    tr += '<tr><td>Min&nbsp;Qty:</td><td align="right">'+libtuj.FormatQuantity(this.points[2].point.low, true)+'</td></tr>';
                    tr += '<tr><td>Avg&nbsp;Qty:</td><td align="right">'+libtuj.FormatQuantity(this.points[1].y, true)+'</td></tr>';
                    tr += '<tr><td>Max&nbsp;Qty:</td><td align="right">'+libtuj.FormatQuantity(this.points[2].point.high, true)+'</td></tr>';
                    tr += '</table>';
                    return tr;
                    // &lt;br/&gt;&lt;span style="color: #990000"&gt;Quantity: '+this.points[1].y+'&lt;/span&gt;<xsl:if test="itemgraphs/d[@matsprice != '']">&lt;br/&gt;&lt;span style="color: #999900"&gt;Materials Price: '+this.points[2].y.toFixed(2)+'g&lt;/span&gt;</xsl:if>';
                },
                useHTML: true,
                positioner: function(w,h,p)
                {
                    var x = p.plotX, y = p.plotY;
                    if (y < 0)
                        y = 0;
                    if (x < (this.chart.plotWidth/2))
                        x += w/2;
                    else
                        x -= w*1.25;
                    return {x: x, y: y};
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
            series: [{
                type: 'candlestick',
                name: 'Market Price',
                color: '#CCCCFF',
                lineColor: '#0000FF',
                data: hcdata.ohlc
            },{
                type: 'line',
                name: 'Quantity',
                yAxis: 1,
                color: '#FF3333',
                data: hcdata.quantity,
                lineWidth: 2
            },{
                type: 'arearange',
                name: 'Quantity Range',
                yAxis: 1,
                color: '#FFCCCC',
                data: hcdata.quantityRange
            },{
                type: 'line',
                name: 'Market Price',
                color: '#009900',
                data: hcdata.price
            }]
        });
    }

    function ItemPriceHeatMap(data, dest)
    {
        var hcdata = {minVal: undefined, maxVal: 0, days: {}, heat: [], categories: {
            x: ['Midnight - 3am','3am - 6am','6am - 9am','9am - Noon','Noon - 3pm','3pm - 6pm','6pm - 9pm','9pm - Midnight'],
            y: ['Saturday','Friday','Thursday','Wednesday','Tuesday','Monday','Sunday']
        }};

        var CalcAvg = function(a)
        {
            if (a.length == 0)
                return null;
            var s = 0;
            for (var x = 0; x < a.length; x++)
                s += a[x];
            return s/a.length;
        }

        var d, wkdy, hr, lastprice;
        for (wkdy = 0; wkdy <= 6; wkdy++)
        {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr <= 7; hr++)
                hcdata.days[wkdy][hr] = [];
        }

        for (var x = 0; x < data.history.length; x++)
        {
            if (typeof lastprice == 'undefined')
                lastprice = data.history[x].price;

            var d = new Date(data.history[x].snapshot*1000);
            wkdy = 6-d.getDay();
            hr = Math.floor(d.getHours()/3);
            hcdata.days[wkdy][hr].push(data.history[x].price);
        }

        var p;
        for (wkdy = 0; wkdy <= 6; wkdy++)
            for (hr = 0; hr <= 7; hr++)
            {
                if (hcdata.days[wkdy][hr].length == 0)
                    p = lastprice;
                else
                    p = Math.round(CalcAvg(hcdata.days[wkdy][hr]));

                lastprice = p;
                hcdata.heat.push([hr, wkdy, p/10000]);
                hcdata.minVal = (typeof hcdata.minVal == 'undefined' || hcdata.minVal > p/10000) ? p/10000 : hcdata.minVal;
                hcdata.maxVal = hcdata.maxVal < p/10000 ? p/10000 : hcdata.maxVal;
            }

        $(dest).highcharts({

            chart: {
                type: 'heatmap'
            },

            title: {
                text: null
            },

            xAxis: {
                categories: hcdata.categories.x
            },

            yAxis: {
                categories: hcdata.categories.y,
                title: null
            },

            colorAxis: {
                min: hcdata.minVal,
                max: hcdata.maxVal,
                minColor: '#FFFFFF',
                maxColor: '#6666FF'
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
                shared: true,
                formatter: function() {
                    return hcdata.categories.y[this.point.y] + ' ' + hcdata.categories.x[this.point.x] + ': ' + libtuj.FormatPrice(this.point.value*10000, true);
                }
            },

            series: [{
                name: 'Market Price',
                borderWidth: 1,
                borderColor: '#FFFFFF',
                data: hcdata.heat,
                dataLabels: {
                    enabled: true,
                    color: 'black',
                    style: {
                        textShadow: 'none',
                        HcTextStroke: null
                    },
                    formatter: function() { return ''+libtuj.FormatPrice(this.point.value*10000, true); }
                }
            }]

        });
    }

    function ItemQuantityHeatMap(data, dest)
    {
        var hcdata = {minVal: undefined, maxVal: 0, days: {}, heat: [], categories: {
            x: ['Midnight - 3am','3am - 6am','6am - 9am','9am - Noon','Noon - 3pm','3pm - 6pm','6pm - 9pm','9pm - Midnight'],
            y: ['Saturday','Friday','Thursday','Wednesday','Tuesday','Monday','Sunday']
        }};

        var CalcAvg = function(a)
        {
            if (a.length == 0)
                return null;
            var s = 0;
            for (var x = 0; x < a.length; x++)
                s += a[x];
            return s/a.length;
        }

        var d, wkdy, hr, lastqty;
        for (wkdy = 0; wkdy <= 6; wkdy++)
        {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr <= 7; hr++)
                hcdata.days[wkdy][hr] = [];
        }

        for (var x = 0; x < data.history.length; x++)
        {
            if (typeof lastqty == 'undefined')
                lastqty = data.history[x].quantity;

            var d = new Date(data.history[x].snapshot*1000);
            wkdy = 6-d.getDay();
            hr = Math.floor(d.getHours()/3);
            hcdata.days[wkdy][hr].push(data.history[x].quantity);
        }

        var p;
        for (wkdy = 0; wkdy <= 6; wkdy++)
            for (hr = 0; hr <= 7; hr++)
            {
                if (hcdata.days[wkdy][hr].length == 0)
                    p = lastqty;
                else
                    p = Math.round(CalcAvg(hcdata.days[wkdy][hr]));

                lastqty = p;
                hcdata.heat.push([hr, wkdy, p]);
                hcdata.minVal = (typeof hcdata.minVal == 'undefined' || hcdata.minVal > p) ? p : hcdata.minVal;
                hcdata.maxVal = hcdata.maxVal < p ? p : hcdata.maxVal;
            }

        $(dest).highcharts({

            chart: {
                type: 'heatmap'
            },

            title: {
                text: null
            },

            xAxis: {
                categories: hcdata.categories.x
            },

            yAxis: {
                categories: hcdata.categories.y,
                title: null
            },

            colorAxis: {
                min: hcdata.minVal,
                max: hcdata.maxVal,
                minColor: '#FFFFFF',
                maxColor: '#FF6666'
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
                shared: true,
                formatter: function() {
                    return hcdata.categories.y[this.point.y] + ' ' + hcdata.categories.x[this.point.x] + ': ' + libtuj.FormatQuantity(this.point.value, true);
                }
            },

            series: [{
                name: 'Quantity',
                borderWidth: 1,
                borderColor: '#FFFFFF',
                data: hcdata.heat,
                dataLabels: {
                    enabled: true,
                    color: 'black',
                    style: {
                        textShadow: 'none',
                        HcTextStroke: null
                    },
                    formatter: function() { return ''+libtuj.FormatQuantity(this.point.value, true); }
                }
            }]

        });
    }

    function ItemGlobalNowColumns(data, dest)
    {
        var hcdata = {categories: [], price: [], quantity: [], lastseen: [], houses: []};
        var allPrices = [];
        var allQuantities = [];
        data.globalnow.sort(function(a,b){ return (b.price - a.price) || (b.quantity - a.quantity); });

        var isThisHouse = false;
        for (var x = 0; x < data.globalnow.length; x++)
        {
            isThisHouse = data.globalnow[x].house == tuj.realms[params.realm].house;

            hcdata.categories.push(data.globalnow[x].house);
            hcdata.quantity.push(data.globalnow[x].quantity);
            hcdata.price.push(isThisHouse ? {
                y: data.globalnow[x].price,
                dataLabels: {
                    enabled: true,
                    formatter: function() {
                        return '<b>' + tuj.realms[params.realm].name + '</b>';
                    },
                    backgroundColor: '#FFFFFF',
                    borderColor: '#000000',
                    borderRadius: 2,
                    borderWidth: 1
                }} : data.globalnow[x].price);
            hcdata.lastseen.push(data.globalnow[x].lastseen);
            hcdata.houses.push(data.globalnow[x].house);

            allQuantities.push(data.globalnow[x].quantity);
            allPrices.push(data.globalnow[x].price);
        }

        allPrices.sort(function(a,b){ return a - b; });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.priceMaxVal = Math.min(allPrices.pop(), q3 + (2.5 * iqr));

        allQuantities.sort(function(a,b){ return a - b; });
        var q1 = allQuantities[Math.floor(allQuantities.length * 0.25)];
        var q3 = allQuantities[Math.floor(allQuantities.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.quantityMaxVal = q3 + (1.5 * iqr);

        var PriceClick = function(houses, evt){
            var realm;
            for (var x in tuj.realms)
                if (tuj.realms.hasOwnProperty(x) && tuj.realms[x].house == houses[evt.point.x])
                {
                    realm = tuj.realms[x].id;
                    break;
                }
            if (realm)
                tuj.SetParams({realm: realm});
        };

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts({
            chart: {
                zoomType: 'x'
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in'
            },
            xAxis: {
                labels: {
                    enabled: false
                }
            },
            yAxis: [{
                title: {
                    text: 'Market Price',
                    style: {
                        color: '#0000FF'
                    }
                },
                min: 0,
                max: hcdata.priceMaxVal,
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatPrice(this.value, true); }
                }
            },{
                title: {
                    text: 'Quantity',
                    style: {
                        color: '#FF3333'
                    }
                },
                min: 0,
                max: hcdata.quantityMaxVal,
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatQuantity(this.value, true); }
                },
                opposite: true
            }],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function() {
                    var realmNames = libtuj.GetRealmsForHouse(hcdata.houses[this.x], 40);
                    var tr = '<b>'+realmNames+'</b>';
                    tr += '<br><span style="color: #000099">Market Price: '+libtuj.FormatPrice(this.points[0].y, true)+'</span>';
                    tr += '<br><span style="color: #990000">Quantity: '+libtuj.FormatQuantity(this.points[1].y, true)+'</span>';
                    tr += '<br><span style="color: #990000">Last seen: '+libtuj.FormatDate(hcdata.lastseen[this.x], true)+'</span>';
                    return tr;
                },
                useHTML: true
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
            series: [{
                type: 'line',
                name: 'Market Price',
                color: '#CCCCFF',
                lineColor: '#0000FF',
                data: hcdata.price,
                yAxis: 0,
                zIndex: 2,
                events: {
                    click: PriceClick.bind(null, hcdata.houses)
                }
            },{
                type: 'column',
                name: 'Quantity',
                color: '#FF9999',
                data: hcdata.quantity,
                zIndex: 1,
                yAxis: 1,
                events: {
                    click: PriceClick.bind(null, hcdata.houses)
                }
            }]
        });
    }

    function ItemAuctions(data, dest)
    {
        var t,tr,td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quantity';
        $(td).text('Quantity');

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text('Bid Each');

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text('Buyout Each');

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'seller';
        $(td).text('Seller');

        data.auctions.sort(function(a,b){
            return Math.floor(a.buy / a.quantity) - Math.floor(b.buy / b.quantity) ||
                Math.floor(a.bid / a.quantity) - Math.floor(b.bid / b.quantity) ||
                a.quantity - b.quantity ||
                tuj.realms[a.sellerrealm].name.localeCompare(tuj.realms[b.sellerrealm].name) ||
                a.sellername.localeCompare(b.sellername);
        });

        var s, a, stackable = data.stats.stacksize > 1;
        for (var x = 0, auc; auc = data.auctions[x]; x++)
        {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quantity';
            td.appendChild(libtuj.FormatQuantity(auc.quantity));

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.bid / auc.quantity);
            if (stackable && auc.quantity > 1)
            {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatFullPrice(auc.bid, true) + ' total';
                a.appendChild(s);
            }
            else
                a = s;
            td.appendChild(a);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.buy / auc.quantity);
            if (stackable && auc.quantity > 1 && auc.buy)
            {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatFullPrice(auc.buy, true) + ' total';
                a.appendChild(s);
            }
            else if (!auc.buy)
                a = libtuj.ce('span');
            else
                a = s;
            if (a)
                td.appendChild(a);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'seller';
            if (auc.sellerrealm)
            {
                a = libtuj.ce('a');
                a.href = tuj.BuildHash({realm: auc.sellerrealm, page: 'seller', id: auc.sellername});
            }
            else
                a = libtuj.ce('span');
            td.appendChild(a);
            $(a).text(auc.sellername + (auc.sellerrealm && auc.sellerrealm != params.realm ? (' - ' + tuj.realms[auc.sellerrealm].name) : ''));
        }

        dest.appendChild(t);
    }
    this.load(tuj.params);
}

tuj.page_item = new TUJ_Item();

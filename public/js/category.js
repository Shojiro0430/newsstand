
var TUJ_Category = function()
{
    var params;
    var lastResults = [];
    var resultFunctions = {};

    this.load = function(inParams)
    {
        params = {};
        for (var p in inParams)
            if (inParams.hasOwnProperty(p))
                params[p] = inParams[p];

        var qs = {
            house: tuj.realms[params.realm].house * tuj.validFactions[params.faction],
            id: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++)
            if (lastResults[x].hash == hash)
            {
                CategoryResult(false, lastResults[x].data);
                return;
            }

        var categoryPage = $('#category-page')[0];
        if (!categoryPage)
        {
            categoryPage = libtuj.ce();
            categoryPage.id = 'category-page';
            categoryPage.className = 'page';
            $('#main').append(categoryPage);
        }

        if (!params.id)
        {
            CategoryFrontPage();
            return;
        }

        $('#progress-page').show();

        $.ajax({
            data: qs,
            success: function(d) {
                if (d.captcha)
                    tuj.AskCaptcha(d.captcha);
                else
                    CategoryResult(hash, d);
            },
            complete: function() {
                $('#progress-page').hide();
            },
            url: 'api/category.php'
        });
    }

    function CategoryResult(hash, dta)
    {
        if (hash)
        {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10)
                lastResults.shift();
        }

        var categoryPage = $('#category-page');
        categoryPage.empty();
        categoryPage.show();

        if (!dta.hasOwnProperty('name'))
        {
            $('#page-title').empty().append(document.createTextNode('Category: ' + params.id));
            tuj.SetTitle('Category: ' + params.id);

            var h2 = libtuj.ce('h2');
            categoryPage.append(h2);
            h2.appendChild(document.createTextNode('Category '+ params.id + ' not found.'));

            return;
        }

        $('#page-title').empty().append(document.createTextNode('Category: ' + dta.name));
        tuj.SetTitle('Category: ' + dta.name);

        if (!dta.hasOwnProperty('results'))
            return;

        var f;
        for (var x = 0; f = dta.results[x]; x++)
            if (resultFunctions.hasOwnProperty(f.name))
            {
                d = libtuj.ce();
                d.className = 'category-'+ f.name.toLowerCase();
                categoryPage.append(d);
                resultFunctions[f.name](f.data, d);
            }
    }

    function CategoryFrontPage()
    {
        var categoryPage = $('#category-page');
        categoryPage.empty();
        categoryPage.show();

        $('#page-title').empty().append(document.createTextNode('Categories'));
        tuj.SetTitle('Categories');
    }

    resultFunctions.ItemList = function(data, dest)
    {
        var item, x, t, td, th, tr, a;

        if (!data.items.length)
            return;

        if (!data.hiddenCols)
            data.hiddenCols = {};

        if (!data.visibleCols)
            data.visibleCols = {};

        if (!data['sort'])
            data['sort'] = '';

        var titleColSpan = 5;
        var titleTd;

        t = libtuj.ce('table');
        t.className = 'category-items';
        dest.appendChild(t);

        if (data.hasOwnProperty('name'))
        {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('th');
            td.className = 'title';
            tr.appendChild(td);
            titleTd = td;
            $(td).text(data.name);
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        td.className = 'name';
        tr.appendChild(td);
        td.colSpan=2;
        $(td).text('Name');

        td = libtuj.ce('th');
        td.className = 'quantity';
        tr.appendChild(td);
        $(td).text('Avail');

        if (data.visibleCols.bid)
        {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text('Bid');
            titleColSpan++;
        }

        td = libtuj.ce('th');
        td.className = 'price';
        tr.appendChild(td);
        $(td).text('Current');

        if (!data.hiddenCols.avgprice)
        {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text('Mean')
            titleColSpan++;
        }

        if (data.visibleCols.globalmedian)
        {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text('Global Median');
            titleColSpan++;
        }

        if (!data.hiddenCols.lastseen)
        {
            td = libtuj.ce('th');
            td.className = 'date';
            tr.appendChild(td);
            $(td).text('Last Seen');
            titleColSpan++;
        }

        titleTd.colSpan = titleColSpan;

        switch (data['sort']) {
            case 'none':
                break;

            case 'globalmedian diff':
                data.items.sort(function(a,b){
                    return ((b.globalmedian - b.price) - (a.globalmedian - a.price)) ||
                        ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (a.price - b.price) ||
                        a.name.localeCompare(b.name);
                });
                break;

            case 'lowprice':
                data.items.sort(function(a,b){
                    return ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (a.price - b.price) ||
                        a.name.localeCompare(b.name);
                });
                break;

            default:
                data.items.sort(function(a,b){
                    return ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (b.price - a.price) ||
                        a.name.localeCompare(b.name);
                });
        }

        for (x = 0; item = data.items[x]; x++)
        {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = 'icon/medium/' + item.icon + '.jpg';

            td = libtuj.ce('td');
            td.className = 'name';
            tr.appendChild(td);
            a = libtuj.ce('a');
            td.appendChild(a);
            a.href = tuj.BuildHash({page: 'item', id: item.id});
            a.rel = 'item=' + item.id;
            $(a).text('[' + item.name + ']');

            td = libtuj.ce('td');
            td.className = 'quantity';
            tr.appendChild(td);
            td.appendChild(libtuj.FormatQuantity(item.quantity));

            if (data.visibleCols.bid)
            {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.bid));
            }

            td = libtuj.ce('td');
            td.className = 'price';
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(item.price));

            if (!data.hiddenCols.avgprice)
            {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.avgprice));
            }

            if (data.visibleCols.globalmedian)
            {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.globalmedian));
            }

            if (!data.hiddenCols.lastseen)
            {
                td = libtuj.ce('td');
                td.className = 'date';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatDate(item.lastseen));
            }
        }
    }
    this.load(tuj.params);
}

tuj.page_category = new TUJ_Category();

var libtuj = {
    ce: function(tag) { if (!tag) tag = 'div'; return document.createElement(tag); },
};

var TUJ = function()
{
    var realms;
    var validPages = ['','search','item','seller','category','battlepet'];
    var validFactions = {'alliance': 1, 'horde': -1};
    var params = {
        realm: undefined,
        faction: undefined,
        page: undefined,
        id: undefined
    }
    var hash = {
        sets: 0,
        changes: 0,
        watching: false
    }
    var inMain = false;

    function Main()
    {
        if (inMain)
            return;
        inMain = true;

        if (typeof realms == 'undefined')
        {
            inMain = false;
            $.ajax({
                data: {
                    region: 'US'
                },
                success: function(dta)
                {
                    realms = dta;
                    Main();
                },
                url: 'api/realms.php'
            });
            return;
        }

        ReadParams();

        if ($('#realm-list').length == 0)
        {
            inMain = false;
            DrawRealms();
            return;
        }

        ShowRealmHeader();

        if (!params.realm)
        {
            if (params.faction)
                ChooseFaction(params.faction);
            inMain = false;
            $('#realm-list .realms-column a').each(function() { this.href = '#' + realms[this.rel].slug + '/' + params.faction; });
            $('#realm-list').addClass('show');
            return;
        }
        $('#realm-list').removeClass('show');

        if (!params.page)
        {
            inMain = false;
            ShowRealmFrontPage();
            return;
        }
        else
        {
            $('#front-page').hide();
        }

        inMain = false;
    }

    function ReadParams()
    {
        if (!hash.watching)
            hash.watching = $(window).on('hashchange', ReadParams);

        if (hash.sets > hash.changes)
        {
            hash.changes++;
            return false;
        }

        if (hash.sets != hash.changes)
            return false;

        var p = {
            realm: undefined,
            faction: undefined,
            page: undefined,
            id: undefined
        }

        var h = location.hash.toLowerCase();
        if (h.charAt(0) == '#')
            h = h.substr(1);
        h = h.split('/');

        var y;

        nextParam:
        for (var x = 0; x < h.length; x++)
        {
            if (!p.page)
                for (y = 0; y < validPages.length; y++)
                    if (h[x] == validPages[y])
                    {
                        p.page = y;
                        continue nextParam;
                    }
            if (!p.faction)
                for (y in validFactions)
                    if (validFactions.hasOwnProperty(y) && h[x] == y)
                    {
                        p.faction = y;
                        continue nextParam;
                    }
            if (!p.realm)
                for (y in realms)
                    if (realms.hasOwnProperty(y) && h[x] == realms[y].slug)
                    {
                        p.realm = y;
                        continue nextParam;
                    }
            p.id = h[x];
        }

        if (!SetParams(p))
            Main();
    }

    function SetParams(p)
    {
        if (p)
            for (var x in p)
                if (p.hasOwnProperty(x) && params.hasOwnProperty(x))
                    params[x] = p[x];

        if (!params.page)
            params.id = undefined;
        if (!params.faction)
            params.realm = undefined;

        var h = '';
        if (params.realm)
            h += '/' + realms[params.realm].slug;
        if (params.faction)
            h += '/' + params.faction;
        if (params.page)
            h += '/' + validPages[params.page];
        if (params.id)
            h += '/' + params.id;
        if (h != '')
            h = '#' + h.substr(1);

        if (h != location.hash)
        {
            hash.sets++;
            location.hash = h;
            Main();
            return true;
        }

        return false;
    }

    this.SetPage = function(page, id)
    {
        var pageId;
        if (typeof page == 'string')
        {
            for (var x = 0; x < validPages.length; x++)
                if (validPages[x] == page)
                    pageId = x;
        }
        else
            pageId = page;

        SetParams({page: pageId, id: id});
    };

    function DrawRealms()
    {
        var addResize = false;
        var realmList = $('#realm-list')[0];

        if (!realmList)
        {
            realmList = libtuj.ce();
            realmList.id = 'realm-list';
            $('#main').prepend(realmList);

            var factionPick = libtuj.ce();
            factionPick.id = 'faction-pick';
            realmList.appendChild(factionPick);

            var directions = libtuj.ce();
            directions.className = 'directions';
            factionPick.appendChild(directions);
            $(directions).text('Choose your faction, then realm.');

            var factionAlliance = libtuj.ce('a');
            $(factionAlliance).addClass('alliance').text('Alliance');
            factionAlliance.href = '#alliance';
            factionPick.appendChild(factionAlliance);
            var factionHorde = libtuj.ce('a');
            $(factionHorde).addClass('horde').text('Horde');
            factionHorde.href = '#horde';
            factionPick.appendChild(factionHorde);

            addResize = true;
        }

        $(realmList).addClass('width-test');
        var maxWidth = realmList.clientWidth;
        var oldColCount = realmList.getElementsByClassName('realms-column').length;

        var cols = [];
        var colWidth = 0;
        if (oldColCount == 0)
        {
            cols.push(libtuj.ce());
            cols[0].className = 'realms-column';
            $(realmList).append(cols[0]);

            colWidth = cols[0].offsetWidth;
        }
        else
        {
            colWidth = realmList.getElementsByClassName('realms-column')[0].offsetWidth;
        }
        $(realmList).removeClass('width-test');

        var numCols = Math.floor(maxWidth / colWidth);
        if (numCols == 0)
            numCols = 1;

        if (numCols == oldColCount)
            return;

        if (oldColCount > 0)
            $(realmList).children('.realms-column').remove();

        for (var x = cols.length; x < numCols; x++)
        {
            cols[x] = libtuj.ce();
            cols[x].className = 'realms-column';
            $(realmList).append(cols[x]);
        }

        var cnt = 0;

        for (var x in realms)
        {
            if (!realms.hasOwnProperty(x))
                continue;

            cnt++;
        }

        var a;
        var c = 0;
        for (var x in realms)
        {
            if (!realms.hasOwnProperty(x))
                continue;

            a = libtuj.ce('a');
            a.rel = x;
            $(a).text(realms[x].name);

            $(cols[Math.min(cols.length-1, Math.floor(c++ / cnt * numCols))]).append(a);
        }

        if (addResize)
            optimizedResize.add(DrawRealms);

        Main();
    }

    function ChooseFaction(dta)
    {
        var toRemove = '';
        var toAdd = (dta.hasOwnProperty('data') ? dta.data.addClass : dta);

        for (var f in validFactions)
            if (validFactions.hasOwnProperty(f) && toAdd.indexOf(f) < 0)
                toRemove += (toRemove == '' ? '' : ' ') + f;
        $('#realm-list').addClass(toAdd).removeClass(toRemove);
        SetParams({faction: toAdd});
        Main();
    }

    function ShowRealmHeader()
    {
        var realmHeader = $('#realm-header')[0];
        var realmText;
        if (!realmHeader)
        {
            realmHeader = libtuj.ce();
            realmHeader.id = 'realm-header';
            $('#realm-list').after(realmHeader);

            realmText = libtuj.ce('span');
            $(realmText).click(function() { SetParams({realm: undefined}); });
            $(realmHeader).append(realmText);

            var frontPageLink = libtuj.ce('a');
            $(frontPageLink).text('Home').click(function() { tuj.SetPage(); });

            var form = libtuj.ce('form');
            var i = libtuj.ce('input');
            i.name = 'search';
            i.type = 'text';
            i.placeholder = 'Search for items and sellers';

            $(form).on('submit', function() {
                tuj.SetPage('search', this.search.value);
                return false;
            }).append(i);

            $(realmHeader).append(form).append(frontPageLink);
        }
        else
            realmText = $(realmHeader).children('span')[0];

        if (!params.realm)
        {
            realmHeader.style.display = 'none';
            return;
        }
        realmHeader.style.display = '';

        $(realmText).text(realms[params.realm].name + ' - ' + params.faction.substr(0,1).toUpperCase() + params.faction.substr(1));
    }

    function ShowRealmFrontPage()
    {
        var frontPage = $('#front-page')[0];
        if (!frontPage)
        {
            frontPage = libtuj.ce();
            frontPage.id = 'front-page';
            $('#realm-header').after(frontPage);
        }
        $(frontPage).show();
    }
    Main();
};

var tuj;
$(document).ready(function() {
    tuj = new TUJ();
});

var optimizedResize = (function() {

    var callbacks = [],
        running = false;

    // fired on resize event
    function resize() {

        if (!running) {
            running = true;

            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(runCallbacks);
            } else {
                setTimeout(runCallbacks, 66);
            }
        }

    }

    // run the actual callbacks
    function runCallbacks() {

        for (var x = 0; x < callbacks.length; x++)
            callbacks[x]();

        running = false;
    }

    // adds callback to loop
    function addCallback(callback) {

        if (callback) {
            callbacks.push(callback);
        }

    }

    return {
        add: function(callback) {
            if (callbacks.length == 0)
                window.addEventListener('resize', resize);
            addCallback(callback);
        }
    }
}());

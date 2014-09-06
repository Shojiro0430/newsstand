var libtuj = {
    ce: function(tag) { if (!tag) tag = 'div'; return document.createElement(tag); },
    AddScript: function(url) {
        var s = libtuj.ce('script');
        s.type = 'text/javascript';
        s.src = url;
        document.getElementsByTagName('head')[0].appendChild(s);
    },
    Mean: function(a)
    {
        if (a.length < 1)
            return null;
        var s = 0;
        for (var x = 0; x < a.length; x++)
            s += a[x];
        return s / a.length;
    },
    Median: function(a)
    {
        if (a.length < 1)
            return null;
        if (a.length == 1)
            return a[0];

        a.sort(function(x,y) { return y-x; });
        if (a.length % 2 == 1)
            return a[Math.floor(a.length / 2)];
        else
            return (a[a.length / 2] + a[a.length / 2 + 1]) / 2;
    },
    StdDev: function(a,mn)
    {
        if (typeof mn == 'undefined')
            mn = libtuj.Mean(a);
        var s = 0;
        for (var x = 0; x < a.length; x++)
            s += Math.pow(a[x] - mn, 2);
        return Math.sqrt(s/ a.length);
    },
    FormatPrice: function(amt,justValue)
    {
        var v = '';
        if (typeof amt == 'number') {
            amt = Math.round(amt);
            if (amt >= 100) // 1s
                v = '' + (amt/10000).toFixed(2) + 'g';
            else
                v = ''+amt+'c';
        }
        if (justValue)
            return v;

        var s = libtuj.ce('span');
        s.class = 'price';
        if (v)
            s.appendChild(document.createTextNode(v));
        return s;
    },
    FormatFullPrice: function(amt,justValue)
    {
        var v = '';
        if (typeof amt == 'number') {
            amt = Math.round(amt);
            var g = Math.floor(amt/10000);
            var s = Math.floor((amt % 10000) / 100);
            var c = Math.floor(amt % 100);

            if (g)
                v += '' + g + 'g ';
            if (g || s)
                v += '' + s + 's ';
            v += '' + c + 'c';
        }
        if (justValue)
            return v;

        var s = libtuj.ce('span');
        s.class = 'price full';
        if (v)
            s.appendChild(document.createTextNode(v));
        return s;
    },
    FormatQuantity: function(amt,justValue)
    {
        var v = Number(Math.round(amt)).toLocaleString();
        if (justValue)
            return v;

        var s = libtuj.ce('span');
        if (v)
            s.appendChild(document.createTextNode(v));
        return s;
    },
    FormatDate: function(unix,justValue)
    {
        var v = '', n, a;
        if (unix)
        {
            var dt, now = new Date();
            if (typeof unix == 'string')
                dt = new Date(unix.replace(/^(\d{4}-\d\d-\d\d) (\d\d:\d\d:\d\d)$/, '$1T$2.000Z'));
            else
                dt = new Date(unix*1000);

            var diff = Math.floor((now.getTime() - dt.getTime()) / 1000);
            var suffix = diff < 0 ? ' from now' : ' ago';
            diff = Math.abs(diff);

            if (diff < 60)
                v = '' + (n = diff) + ' second' + (n != 1 ? 's' : '') + suffix;
            else if (diff < 60*60)
                v = '' + (n = Math.round(diff/60)) + ' minute' + (n != 1 ? 's' : '') + suffix;
            else if (diff < 24*60*60)
                v = '' + (n = Math.round(diff/(60*60))) + ' hour' + (n != 1 ? 's' : '') + suffix;
            else if (diff < 10*24*60*60)
                v = '' + (n = Math.round(diff/(24*60*60))) + ' day' + (n != 1 ? 's' : '') + suffix;
            else
                v = dt.toLocaleDateString();
        }
        if (justValue)
            return v;

        var s = libtuj.ce('span');
        if (v)
        {
            a = libtuj.ce('abbr');
            a.className = 'full-date';
            a.title = dt.toLocaleString();
            a.appendChild(document.createTextNode(v));
            s.appendChild(a);
        }
        return s;
    },
    GetRealmsForHouse: function(house, maxLineLength)
    {
        var lineLength = 0;
        var realmNames = '';
        for (var x in tuj.realms)
            if (tuj.realms.hasOwnProperty(x) && tuj.realms[x].house == house)
            {
                if (maxLineLength && lineLength > 0 && lineLength + tuj.realms[x].name.length > maxLineLength)
                {
                    realmNames += '<br>';
                    lineLength = 0;
                }
                lineLength += 2 + tuj.realms[x].name.length;
                realmNames += tuj.realms[x].name + ', ';
            }

        if (realmNames == '')
            realmNames = '(House '+hcdata.houses[this.x]+')';
        else
            realmNames = realmNames.substr(0, realmNames.length - 2);

        return realmNames;
    },
    Storage: {
        Get: function(key)
        {
            if (!window.localStorage)
                return false;

            var v = window.localStorage.getItem(key);
            if (v != null)
                return JSON.parse(v);
            else
                return false;
        },
        Set: function(key, val)
        {
            if (!window.localStorage)
                return false;

            window.localStorage.setItem(key, JSON.stringify(val));
        }
    }
};

var tujConstants = {
    breeds: {
        0: 'All Breeds',
        3: 'B/B',
        4: 'P/P',
        5: 'S/S',
        6: 'H/H',
        7: 'H/P',
        8: 'P/S',
        9: 'H/S',
        10: 'P/B',
        11: 'S/B',
        12: 'H/B'
    },
    qualities: {
        0: 'Poor',
        1: 'Common',
        2: 'Uncommon',
        3: 'Rare',
        4: 'Epic',
        5: 'Legendary',
        6: 'Artifact',
        7: 'Heirloom'
    },
    itemClasses: {
        7: 'Trade Goods',
        0: 'Consumable',
        5: 'Reagent',

        3: 'Gem',
        16: 'Glyph',

        2: 'Weapon',
        4: 'Armor',

        9: 'Recipe',

        1: 'Container',
        11: 'Quiver',

        17: 'Battle Pets',

        12: 'Quest',
        13: 'Key',

        6: 'Projectile',
        8: 'Generic',
        10: 'Money',
        14: 'Permanent',
        15: 'Miscellaneous'
    },
    itemClassOrder: [2,9,6,4,7,3,14,1,15,8,16,10,12,13,17,18,5,11],
    races: {
        10: 'Blood Elves',
        11: 'Draenei',
         3: 'Dwarves',
         7: 'Gnomes',
         9: 'Goblins',
         1: 'Humans',
         4: 'Night Elves',
         2: 'Orcs',
         6: 'Tauren',
         8: 'Trolls',
         5: 'Undead'
    }
}

var TUJ = function()
{
    var validPages = ['','search','item','seller','battlepet','contact','donate','category'];
    var pagesNeedRealm = [true, true, true, true, true, false, false, true];
    this.validFactions = {'alliance': 1, 'horde': -1};
    this.region = undefined;
    this.realms = undefined;
    this.params = {
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
    var self = this;

    function Main()
    {
        if (inMain)
            return;
        inMain = true;

        document.body.className = '';

        if (typeof self.realms == 'undefined')
        {
            inMain = false;

            $('#progress-page').show();

            $.ajax({
                success: function(dta)
                {
                    self.region = dta.region;
                    self.realms = dta.realms;
                    if (typeof self.realms == 'undefined')
                    {
                        alert('Error getting realms');
                        self.realms = [];
                    }
                    Main();
                },
                error: function(xhr, stat, er)
                {
                    alert('Error getting realms: '+stat + ' ' + er);
                    self.realms = [];
                },
                complete: function() {
                    $('#progress-page').hide();
                },
                url: 'api/realms.php'
            });
            return;
        }

        if (self.region == 'EU' && !document.getElementById('eu-warning'))
        {
            var d = libtuj.ce();
            d.id = 'eu-warning';
            document.getElementById('main').insertBefore(d, document.getElementById('main').firstChild);
            var h2 = libtuj.ce('h2');
            d.appendChild(h2);
            h2.appendChild(document.createTextNode('EU Realms Unavailable'));
            d.appendChild(document.createTextNode('While we are back in beta testing, we do not collect any data for EU realms yet.'));
            d.appendChild(libtuj.ce('p'));
            d.appendChild(document.createTextNode('Please check back with us after Blizzard releases the Warlords of Draenor pre-patch!'));
        }

        var ls, firstRun = !hash.watching;
        ReadParams();
        if (firstRun && !self.params.realm && (ls = libtuj.Storage.Get('defaultRealm')))
        {
            inMain = false;
            tuj.SetParams(ls);
            return;
        }

        UpdateSidebar();

        if ($('#realm-list').length == 0)
        {
            inMain = false;
            DrawRealms();
            return;
        }

        $('#main .page').hide();
        $('#realm-list').removeClass('show');
        if (!self.params.realm && (!self.params.page || pagesNeedRealm[self.params.page]))
        {
            if (self.params.faction)
                ChooseFaction(self.params.faction);
            inMain = false;
            $('#faction-pick a').each(function() { this.href = self.BuildHash({realm: undefined, faction: this.rel});});
            $('#realm-list .realms-column a').each(function() { this.href = self.BuildHash({realm: this.rel}); });
            $('#realm-list').addClass('show');
            document.body.className = 'realm';
            return;
        }

        window.scrollTo(0,0);

        if (!self.params.page)
        {
            inMain = false;
            document.body.className = 'front';
            ShowRealmFrontPage();
            return;
        }

        inMain = false;

        document.body.className = validPages[self.params.page];

        if (typeof tuj['page_'+validPages[self.params.page]] == 'undefined')
            libtuj.AddScript('js/'+validPages[self.params.page]+'.js');
        else
            tuj['page_'+validPages[self.params.page]].load(self.params);

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
                for (y in self.validFactions)
                    if (self.validFactions.hasOwnProperty(y) && h[x] == y)
                    {
                        p.faction = y;
                        continue nextParam;
                    }
            if (!p.realm)
                for (y in self.realms)
                    if (self.realms.hasOwnProperty(y) && h[x] == self.realms[y].slug)
                    {
                        p.realm = y;
                        continue nextParam;
                    }
            p.id = h[x];
        }

        if (!self.SetParams(p))
            Main();
    }

    this.SetParams = function(p)
    {
        if (p)
            for (var x in p)
                if (p.hasOwnProperty(x) && self.params.hasOwnProperty(x))
                    self.params[x] = p[x];

        if (typeof self.params.page == 'string')
        {
            for (var x = 0; x < validPages.length; x++)
                if (validPages[x] == self.params.page)
                    self.params.page = x;

            if (typeof self.params.page == 'string')
                self.params.page = undefined;
        }

        if (self.params.realm && !self.params.page)
            libtuj.Storage.Set('defaultRealm', {faction: self.params.faction, realm: self.params.realm});

        var h = self.BuildHash(self.params);

        if (h != location.hash)
        {
            hash.sets++;
            location.hash = h;
            Main();
            return true;
        }

        return false;
    }

    function UpdateSidebar()
    {
        if (self.params.realm)
            $('#topcorner').show();
        else
            $('#topcorner').hide();

        var regionLink = $('#topcorner a.region');
        regionLink[0].href = self.region == 'US' ? '//eu.theunderminejournal.com' : '//theunderminejournal.com';
        regionLink.text(self.region);

        var factionLink = $('#topcorner a.faction')[0];
        factionLink.className = 'faction '+(self.params.faction ? self.params.faction : 'none');
        var otherFaction = self.params.faction ? (self.params.faction == 'alliance' ? 'horde' : 'alliance') : undefined;
        factionLink.href = self.BuildHash({faction: otherFaction});

        var realmLink = $('#topcorner a.realm');
        realmLink[0].href = self.BuildHash({realm: undefined});
        realmLink.text(self.params.realm ? self.realms[self.params.realm].name : '');

        var contactLink = $('#bottom-bar a.contact');
        contactLink[0].href = self.BuildHash({page: 'contact', id: undefined});

        var donateLink = $('#bottom-bar a.donate');
        donateLink[0].href = self.BuildHash({page: 'donate', id: undefined});

        $('#title a')[0].href = self.BuildHash({page: undefined});
        $('#page-title').empty();
        self.SetTitle();

        if ($('#topcorner form').length == 0)
        {
            var form = libtuj.ce('form');
            var i = libtuj.ce('input');
            i.name = 'search';
            i.type = 'text';
            i.placeholder = 'Search';

            $(form).on('submit', function() {
                location.href = self.BuildHash({page: 'search', id: this.search.value.replace('/','')});
                return false;
            }).append(i);

            $('#topcorner .realm-faction').after(form);
        }
    }

    this.SetTitle = function(titlePart)
    {
        var title = '';

        if (titlePart)
            title += titlePart + ' - '
        else if (self.params.page)
        {
            title += validPages[self.params.page].substr(0,1).toUpperCase() + validPages[self.params.page].substr(1);
            if (self.params.id)
                title += ': ' + self.params.id;
            title += ' - ';
        }

        if (self.params.realm)
            title += self.region + ' ' + self.realms[self.params.realm].name + ' ' + self.params.faction.substr(0,1).toUpperCase() + self.params.faction.substr(1) + ' - ';

        document.title = title + 'The Undermine Journal';
    }

    this.BuildHash = function(p)
    {
        var tParams = {};
        for (var x in self.params)
        {
            if (self.params.hasOwnProperty(x))
                tParams[x] = self.params[x];
            if (p.hasOwnProperty(x))
                tParams[x] = p[x];
        }

        if (typeof tParams.page == 'string')
        {
            for (var x = 0; x < validPages.length; x++)
                if (validPages[x] == tParams.page)
                    tParams.page = x;

            if (typeof tParams.page == 'string')
                tParams.page = undefined;
        }

        if (!tParams.page)
            tParams.id = undefined;
        if (!tParams.faction)
            tParams.realm = undefined;

        var h = '';
        if (tParams.realm)
            h += '/' + self.realms[tParams.realm].slug;
        if (tParams.faction)
            h += '/' + tParams.faction;
        if (tParams.page)
            h += '/' + validPages[tParams.page];
        if (tParams.id)
            h += '/' + tParams.id;
        if (h != '')
            h = '#' + h.substr(1).toLowerCase();

        return h;
    }

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
            factionAlliance.rel = 'alliance';
            $(factionAlliance).addClass('alliance').text('Alliance');
            factionPick.appendChild(factionAlliance);
            var factionHorde = libtuj.ce('a');
            $(factionHorde).addClass('horde').text('Horde');
            factionHorde.rel = 'horde';
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

        for (var x in self.realms)
        {
            if (!self.realms.hasOwnProperty(x))
                continue;

            cnt++;
        }

        var a;
        var c = 0;
        for (var x in self.realms)
        {
            if (!self.realms.hasOwnProperty(x))
                continue;

            a = libtuj.ce('a');
            a.rel = x;
            $(a).text(self.realms[x].name);

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

        for (var f in self.validFactions)
            if (self.validFactions.hasOwnProperty(f) && toAdd.indexOf(f) < 0)
                toRemove += (toRemove == '' ? '' : ' ') + f;
        $('#realm-list').addClass(toAdd).removeClass(toRemove);
        self.SetParams({faction: toAdd});
        Main();
    }

    var CaptchaClick = function()
    {
        var i = $(this);
        if (i.hasClass('selected'))
            i.removeClass('selected');
        else
            i.addClass('selected');
    };

    var CaptchaSubmit = function()
    {
        var answer = '';
        var imgs = this.parentNode.getElementsByTagName('img');
        for (var x = 0; x < imgs.length; x++)
        {
            if ($(imgs[x]).hasClass('selected'))
                answer += imgs[x].id.substr(8);
        }

        if (answer == '')
            return;

        $('#progress-page').show();

        $.ajax({
            data: {answer: answer},
            success: function(d) {
                if (d.captcha)
                    tuj.AskCaptcha(d.captcha);
                else
                    Main();
            },
            complete: function() {
                $('#progress-page').hide();
            },
            url: 'api/captcha.php'
        });
    };

    this.AskCaptcha = function(c)
    {
        var captchaPage = $('#captcha-page')[0];
        if (!captchaPage)
        {
            captchaPage = libtuj.ce();
            captchaPage.id = 'captcha-page';
            captchaPage.className = 'page';
            $('#main').append(captchaPage);
        }

        $('#page-title').text('Solve Captcha');
        tuj.SetTitle('Solve Captcha');

        $(captchaPage).empty();

        captchaPage.appendChild(document.createTextNode("You viewed a lot of pages recently. To make sure you're not a script, please select all the "+tujConstants.races[c.lookfor]+" without helms."));

        d = libtuj.ce();
        d.className = 'captcha';
        captchaPage.appendChild(d);

        for (var x = 0; x < c.ids.length; x++)
        {
            var img = libtuj.ce('img');
            img.className = 'captcha-button';
            img.src = 'captcha/'+ c.ids[x]+'.jpg';
            img.id = 'captcha-'+(x+1);
            $(img).click(CaptchaClick);
            d.appendChild(img);
        }

        var b = libtuj.ce('br');
        b.clear = 'all';
        d.appendChild(b);

        b = libtuj.ce('input');
        b.value = 'Submit';
        b.type = 'button';
        $(b).click(CaptchaSubmit);
        d.appendChild(b);

        $(captchaPage).show();
    };

    function ShowRealmFrontPage()
    {
        var frontPage = $('#front-page')[0];
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

var wowhead_tooltips = { "hide": { "extra": true, "sellprice": true } };
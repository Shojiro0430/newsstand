var TUJ_Transmog = function ()
{
    var params;
    var lastResults = [];
    var resultFunctions = {};
    var self = this;
    var typeNames = [];

    var validPages = {
        'cloth': 'Cloth Armor',
        'leather': 'Leather Armor',
        'mail': 'Mail Armor',
        'plate': 'Plate Armor',
        'main': 'Main-hand Weapons',
        'off': 'Off-hand Weapons',
    };

    this.load = function (inParams)
    {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        var qs = {
            house: tuj.realms[params.realm].house,
            id: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++) {
            if (lastResults[x].hash == hash) {
                TransmogResult(false, lastResults[x].data);
                return;
            }
        }

        var transmogPage = $('#transmog-page')[0];
        if (!transmogPage) {
            transmogPage = libtuj.ce();
            transmogPage.id = 'transmog-page';
            transmogPage.className = 'page';
            $('#main').append(transmogPage);
        }

        if ((!params.id) || (!validPages.hasOwnProperty(params.id))) {
            tuj.SetParams({id: 'main'}, true);
            return;
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
                    TransmogResult(hash, d);
                }
            },
            complete: function ()
            {
                $('#progress-page').hide();
            },
            url: 'api/transmog.php'
        });
    }

    function TransmogResult(hash, dta)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var transmogPage = $('#transmog-page');
        transmogPage.empty();
        transmogPage.show();

        $('#page-title').empty().append(document.createTextNode('Transmog: ' + validPages[params.id]));
        tuj.SetTitle('Transmog: ' + validPages[params.id]);

        //transmogPage.append(libtuj.Ads.Add('8323200718'));

        typeNames = [];
        for (var k in dta) {
            if (dta.hasOwnProperty(k)) {
                typeNames.push(k);
            }
        }
        typeNames.sort(function(a,b) { return a.localeCompare(b); });

        if (typeNames.length > 1) {
            d = libtuj.ce();
            d.className = 'transmog-slots';
            transmogPage.append(d);

            for (var x = 0; x < typeNames.length; x++) {
                a = libtuj.ce('a');
                d.appendChild(a);
                a.id = 'transmog-slot-choice-' + x;
                $(a).click(self.showType.bind(self, x));
                a.appendChild(document.createTextNode(typeNames[x]));
                if (x == 0) {
                    a.className = 'selected';
                }
            }
        }

        var itemSort = function(a,b) {
            return (a.buy - b.buy) || (a.id - b.id);
        }

        for (var x = 0; x < typeNames.length; x++) {
            d = libtuj.ce();
            d.className = 'transmog-results';
            d.id = 'transmog-slot-results-' + x;
            transmogPage.append(d);
            if (x > 0) {
                $(d).hide();
            }

            var items = dta[typeNames[x]];
            items.sort(itemSort);

            for (var y = 0; y < items.length; y++) {
                var box = libtuj.ce();
                box.className = 'transmog-box';
                d.appendChild(box);

                var img = libtuj.ce('a');
                img.className = 'transmog-img';
                box.appendChild(img);
                img.href = tuj.BuildHash({page: 'item', id: items[y].id});
                img.style.backgroundImage = 'url(' + tujCDNPrefix + 'models/' + items[y].display + '.png)';

                var prc = libtuj.ce('a');
                box.appendChild(prc);
                prc.href = img.href;
                prc.rel = 'item=' + items[y].id;
                prc.appendChild(libtuj.FormatPrice(items[y].buy));
            }
        }

        var s = libtuj.ce();
        s.style.textAlign = 'center';
        s.appendChild(document.createTextNode('Images generated by '));
        var a = libtuj.ce('a');
        a.href = 'http://www.wowhead.com/';
        $(a).text('Wowhead');
        s.appendChild(a);
        transmogPage.append(s);

        //libtuj.Ads.Show();
    }

    this.showType = function(idx) {
        $('.transmog-slots a').removeClass('selected');
        $('#transmog-slot-choice-'+idx).addClass('selected');

        $('.transmog-results').hide();
        $('#transmog-slot-results-'+idx).show();
    }

    this.load(tuj.params);
}

tuj.page_transmog = new TUJ_Transmog();

var TUJ_Subscription = function ()
{
    var params;
    var formElements;
    var self;

    var subData;

    this.load = function (inParams)
    {
        self = this;
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        $('#page-title').text(tuj.lang.subscription);
        tuj.SetTitle(tuj.lang.subscription);

        var userName = tuj.LoggedInUserName();
        if (userName) {
            ShowLoggedInAs(userName);
            $('#subscription-description').hide();
            $('#subscription-settings').empty().hide();
            FetchSubscriptionSettings();
        } else {
            ShowLoginForm();
            $('#subscription-description').show();
            $('#subscription-settings').empty().hide();
        }

        ShowSubscriptionMessage(params.id);

        $('#subscription-page').show();
    };

    function ShowSubscriptionMessage(id)
    {
        if (id && tuj.lang.SubscriptionErrors.hasOwnProperty(id)) {
            $('#subscription-alert').empty().html(tuj.lang.SubscriptionErrors[id]).show();
        } else {
            $('#subscription-alert').empty().hide();
        }
    }

    function ShowSubscriptionSettings(dta)
    {
        subData = dta;

        var settingsParent = $('#subscription-settings');
        settingsParent.empty().hide();

        var settingsMessages = libtuj.ce('div');
        settingsMessages.id = 'subscription-messages';
        settingsParent.append(settingsMessages);
        ShowMessages(settingsMessages);

        var settingsEmail = libtuj.ce('div');
        settingsEmail.id = 'subscription-email';
        settingsParent.append(settingsEmail);
        ShowEmail(settingsEmail);

        var settingsWatches = libtuj.ce('div');
        settingsWatches.id = 'subscription-watches';
        settingsParent.append(settingsWatches);
        ShowWatches(settingsWatches);

        settingsParent.show();
    }

    function SetEmailAddress()
    {
        var addressBox = document.getElementById('subscription-email-address');
        if (addressBox.value == addressBox.defaultValue) {
            return;
        }

        $.ajax({
            data: {'emailaddress': addressBox.value},
            type: 'POST',
            success: function(dta) {
                if (dta.hasOwnProperty('address')) {
                    subData.email.address = addressBox.value = dta.address;
                }
                addressBox.defaultValue = addressBox.value;

                if (dta.hasOwnProperty('status')) {
                    if (dta.status == 'verify') {
                        $('#subscription-email').addClass('verify');
                    } else {
                        $('#subscription-email').removeClass('verify');
                    }
                    if (tuj.lang.EmailStatus.hasOwnProperty(dta.status)) {
                        alert(tuj.lang.EmailStatus[dta.status]);
                    }
                }
            },
            error: function() {
                addressBox.value = addressBox.defaultValue;
                alert(tuj.lang.EmailStatus.unknown);
            },
            url: 'api/subscription.php'
        });
    }

    function VerifyEmailAddress()
    {
        var addressBox = document.getElementById('subscription-email-address');
        var verifyBox = document.getElementById('subscription-email-verification-code');
        if (verifyBox.value == '') {
            return;
        }

        $.ajax({
            data: {'verifyemail': verifyBox.value},
            type: 'POST',
            success: function(dta) {
                verifyBox.value = '';
                if (dta.hasOwnProperty('address')) {
                    subData.email.address = addressBox.value = dta.address;
                }
                addressBox.defaultValue = addressBox.value;

                if (dta.hasOwnProperty('status')) {
                    if (dta.status == 'verify') {
                        $('#subscription-email').addClass('verify');
                    } else {
                        $('#subscription-email').removeClass('verify');
                    }
                    if (tuj.lang.EmailStatus.hasOwnProperty(dta.status)) {
                        alert(tuj.lang.EmailStatus[dta.status]);
                    }
                }
            },
            error: function() {
                verifyBox.value = '';
                alert(tuj.lang.EmailStatus.unknown);
            },
            url: 'api/subscription.php'
        });
    }

    function ShowEmail(container)
    {
        var settings = subData.email;

        var f = libtuj.ce('form');
        container.appendChild(f);

        var s = libtuj.ce('span');
        $(s).text(tuj.lang.emailAddress + ': ');
        f.appendChild(s);

        var i = libtuj.ce('input');
        i.type = 'email';
        i.id = 'subscription-email-address';
        i.value = i.defaultValue = (settings && settings.address) ? settings.address : '';
        f.appendChild(i);

        var i = libtuj.ce('input');
        i.type = 'button';
        i.id = 'subscription-email-set-button';
        i.value = tuj.lang.setAddress;
        $(i).on('click', SetEmailAddress);
        f.appendChild(i);

        var d = libtuj.ce('div');
        d.id = 'subscription-email-verification';
        f.appendChild(d);

        var s = libtuj.ce('span');
        $(s).text(tuj.lang.emailVerification + ': ');
        d.appendChild(s);

        var i = libtuj.ce('input');
        i.type = 'number';
        i.id = 'subscription-email-verification-code';
        i.value = '';
        i.maxLength = '16';
        d.appendChild(i);

        var i = libtuj.ce('input');
        i.type = 'button';
        i.id = 'subscription-email-verify-button';
        i.value = tuj.lang.verifyAddress;
        $(i).on('click', VerifyEmailAddress);
        d.appendChild(i);

        if (settings.needVerification) {
            $('#subscription-email').addClass('verify');
        } else {
            $('#subscription-email').removeClass('verify');
        }
    }

    function ShowWatches(container)
    {
        var watches = subData.watches;

        if (!watches.length) {
            return;
        }

        // TODO
    }

    function ShowMessages(container)
    {
        var listDiv = libtuj.ce('div');
        listDiv.id = 'subscription-messages-list';
        container.appendChild(listDiv);

        var messageDiv = libtuj.ce('div');
        messageDiv.id = 'subscription-messages-window';
        container.appendChild(messageDiv);

        subData.messages.sort(function(a,b){
            return b.seq - a.seq;
        });

        var msgDiv, s;
        for (var x = 0, msg; msg = subData.messages[x]; x++) {
            msg.div = msgDiv = libtuj.ce('div');
            msgDiv.className = 'messages-list-item';
            listDiv.appendChild(msgDiv);

            s = libtuj.ce('span');
            s.className = 'message-subject';
            $(s).text(msg.subject);
            msgDiv.appendChild(s);

            s = libtuj.FormatDate(msg.created);
            s.className += ' message-date';
            msgDiv.appendChild(s);

            $(msgDiv).data('seq', msg.seq).click(ShowMessage.bind(self, x));
        }
        if (subData.messages.length) {
            ShowMessage(0);
        }
    }

    function ShowMessage(idx)
    {
        $('#subscription-messages-list .messages-list-item.selected').removeClass('selected');

        var msg = subData.messages[idx];
        $(msg.div).addClass('selected');

        var d = libtuj.ce('div');
        d.className = 'message-subject';
        $(d).text(msg.subject);

        var s = libtuj.FormatDate(msg.created);
        s.className += ' message-date';
        d.appendChild(s);

        $('#subscription-messages-window').empty().append(d).append('<div class="message-text"></div>');

        if (msg.hasOwnProperty('message')) {
            $('#subscription-messages-window .message-text').html(msg.message);
            return;
        }

        $.ajax({
            data: {'getmessage': msg.seq},
            type: 'POST',
            success: function(dta) {
                msg.message = dta.message;
                ShowMessage(idx);
            },
            error: function() {
                $('#subscription-messages-window .message-text').html(tuj.lang.subMessageFetchError);
            },
            url: 'api/subscription.php'
        });
    }

    function ShowLoggedInAs(userName)
    {
        var logOut = libtuj.ce('input');
        logOut.type = 'button';
        logOut.value = tuj.lang.logOut;
        $(logOut).click(tuj.LogOut);

        $('#subscription-login').empty().html(libtuj.sprintf(tuj.lang.loggedInAs, userName) + ' ').append(logOut);
    }

    function ShowLoginForm()
    {
        formElements = {};

        var region = tuj.validRegions[0];
        if (params.region != undefined) {
            region = tuj.validRegions[params.region];
        }

        var i, f = formElements.form = libtuj.ce('form');
        f.method = 'GET';
        $('#subscription-login').empty().append(f);

        i = formElements.clientId = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'client_id';
        $(f).append(i);

        i = formElements.scope = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'scope';
        $(f).append(i);

        i = formElements.state = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'state';
        $(f).append(i);

        i = formElements.redirectUri = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'redirect_uri';
        $(f).append(i);

        i = formElements.responseType = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'response_type';
        i.value = 'code';
        $(f).append(i);

        i = formElements.submit = libtuj.ce('input');
        i.type = 'button';
        i.value = tuj.lang.logInBattleNet;
        $(i).click(FetchStateAndSubmit.bind(self, formElements, region));
        $(f).append(i);
    }

    function FetchStateAndSubmit(formElements, region)
    {
        $.ajax({
            data: {
                'loginfrom': tuj.BuildHash({}),
                'region': region,
            },
            type: 'POST',
            success: function (d) {
                if (!d.state) {
                    LoginFail();
                    return;
                }
                formElements.clientId.value = d.clientId;
                formElements.state.value = d.state;
                formElements.redirectUri.value = d.redirectUri;
                formElements.submit.disabled = true;
                formElements.form.action = d.authUri.replace('%s', region.toLowerCase());
                formElements.form.submit();
            },
            error: LoginFail,
            url: 'api/subscription.php'
        });
    }

    function FetchSubscriptionSettings()
    {
        subData = {};
        $.ajax({
            data: {'settings': 1},
            type: 'POST',
            success: ShowSubscriptionSettings,
            error: SettingsFail,
            url: 'api/subscription.php'
        });
    }

    function LoginFail()
    {
        $('#subscription-login').empty().html('Error setting up login, please try again later.');
    }

    function SettingsFail()
    {
        $('#subscription-settings').empty().html(tuj.lang.SubscriptionErrors.nodata);
    }

    this.load(tuj.params);
}

tuj.page_subscription = new TUJ_Subscription();

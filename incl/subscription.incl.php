<?php

require_once('incl.php');
require_once('memcache.incl.php');
require_once('subscription.credentials.php');

define('SUBSCRIPTION_LOGIN_COOKIE', 'session');
define('SUBSCRIPTION_CSRF_COOKIE', 'csrf');
define('SUBSCRIPTION_SESSION_LENGTH', 1209600); // 2 weeks

define('SUBSCRIPTION_MESSAGES_CACHEKEY', 'submessage_');
define('SUBSCRIPTION_MESSAGES_MAX', 50);

define('SUBSCRIPTION_ITEM_CACHEKEY', 'subitem_');
define('SUBSCRIPTION_SPECIES_CACHEKEY', 'subspecies_');
define('SUBSCRIPTION_WATCH_CACHEKEY', 'subwatch_');
define('SUBSCRIPTION_REPORTS_CACHEKEY', 'subreports_');

define('SUBSCRIPTION_WATCH_LIMIT_PER', 5);
define('SUBSCRIPTION_WATCH_LIMIT_TOTAL', 1000);

define('SUBSCRIPTION_WATCH_MAX_PERIOD', 1435); // minutes
define('SUBSCRIPTION_WATCH_MIN_PERIOD', 2); // minutes
define('SUBSCRIPTION_WATCH_MIN_PERIOD_FREE', 475); // lowest period for free subs
define('SUBSCRIPTION_WATCH_DEFAULT_PERIOD', 715); // minutes
define('SUBSCRIPTION_WATCH_FREE_LAST_LOGIN_DAYS', 30); // max number of days since we've seen this free sub and we still trigger notifications

function GetLoginState($logOut = false) {
    $userInfo = [];
    if (!isset($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE])) {
        return $userInfo;
    }
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE], 0, 24));
    if (strlen($state) != 24) {
        return $userInfo;
    }

    $stateBytes = base64_decode(strtr($state, '-_', '+/'));

    if ($logOut) {
        MCDelete('usersession_'.$state);

        $db = DBConnect();
        $stmt = $db->prepare('DELETE FROM tblUserSession WHERE session=?');
        $stmt->bind_param('s', $stateBytes);
        $stmt->execute();
        $stmt->close();
    } else {
        $userInfo = MCGet('usersession_'.$state);
        if ($userInfo === false) {
            $db = DBConnect();

            // see also MakeNewSession in api/subscription.php
            $stmt = $db->prepare('SELECT u.id, u.name, unix_timestamp(u.paiduntil) paiduntil FROM tblUserSession us join tblUser u on us.user=u.id WHERE us.session=?');
            $stmt->bind_param('s', $stateBytes);
            $stmt->execute();
            $result = $stmt->get_result();
            $userInfo = DBMapArray($result);
            $stmt->close();

            if (count($userInfo) < 1) {
                $logOut = true;
            } else {
                $userInfo = array_pop($userInfo);
                MCSet('usersession_'.$state, $userInfo);

                $ip = substr($_SERVER['REMOTE_ADDR'], 0, 40);
                $ua = substr($_SERVER['HTTP_USER_AGENT'], 0, 250);

                $stmt = $db->prepare('UPDATE tblUserSession SET lastseen=NOW(), ip=?, useragent=? WHERE session=?');
                $stmt->bind_param('sss', $ip, $ua, $stateBytes);
                $stmt->execute();
                $stmt->close();

                $stmt = $db->prepare('UPDATE tblUser SET lastseen=NOW() WHERE id=?');
                $stmt->bind_param('i', $userInfo['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($logOut) {
        setcookie(SUBSCRIPTION_LOGIN_COOKIE, '', time() - SUBSCRIPTION_SESSION_LENGTH, '/api/', '', true, true);
        setcookie(SUBSCRIPTION_CSRF_COOKIE, '', 0, '/api/csrf.txt', '', true, false);
        return [];
    }

    if (!headers_sent()) {
        setcookie(SUBSCRIPTION_LOGIN_COOKIE, $state, time() + SUBSCRIPTION_SESSION_LENGTH, '/api/', '', true, true);
        setcookie(SUBSCRIPTION_CSRF_COOKIE, strtr(base64_encode(hash_hmac('sha256', $stateBytes, SUBSCRIPTION_CSRF_HMAC_KEY, true)), '+/=', '-_.'), 0, '/api/csrf.txt', '', true, false);
    }

    return $userInfo;
}

function ValidateCSRFProtectedRequest()
{
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return false;
    }
    if (strlen($_SERVER['HTTP_X_CSRF_TOKEN']) > 100) {
        return false;
    }
    if (!isset($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE])) {
        return false;
    }
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE], 0, 24));
    if (strlen($state) != 24) {
        return false;
    }

    $stateBytes = base64_decode(strtr($state, '-_', '+/'));
    $correctToken = strtr(base64_encode(hash_hmac('sha256', $stateBytes, SUBSCRIPTION_CSRF_HMAC_KEY, true)), '+/=', '-_.');

    if (function_exists('hash_equals')) {
        return hash_equals($correctToken, $_SERVER['HTTP_X_CSRF_TOKEN']);
    }
    return (sha1($correctToken) === sha1($_SERVER['HTTP_X_CSRF_TOKEN'])); // sha1 on both sides to mitigate timing attacks
}

function ClearLoginStateCache()
{
    if (!isset($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE])) {
        return false;
    }
    $state = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_COOKIE[SUBSCRIPTION_LOGIN_COOKIE], 0, 24));
    if (strlen($state) != 24) {
        return false;
    }

    MCDelete('usersession_'.$state);
    return true;
}

function SendUserMessage($userId, $messageType, $subject, $message)
{
    $loops = 0;
    while (!MCAdd(SUBSCRIPTION_MESSAGES_CACHEKEY . "lock_$userId", 1, 15)) {
        usleep(250000);
        if ($loops++ >= 120) { // 30 seconds
            return false;
        }
    }

    $db = DBConnect(true);
    $seq = 0;
    $cnt = 0;
    $stmt = $db->prepare('select ifnull(max(seq),0)+1, count(*) from tblUserMessages where user = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($seq, $cnt);
    $stmt->fetch();
    $stmt->close();

    $stmt = $db->prepare('INSERT INTO tblUserMessages (user, seq, created, type, subject, message) VALUES (?, ?, NOW(), ?, ?, ?)');
    $stmt->bind_param('iisss', $userId, $seq, $messageType, $subject, $message);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        $cnt++;
    }
    if ($cnt > SUBSCRIPTION_MESSAGES_MAX) {
        $stmt = $db->prepare('delete from tblUserMessages where user = ? order by seq asc limit ?');
        $cnt = $cnt - SUBSCRIPTION_MESSAGES_MAX;
        $stmt->bind_param('ii', $userId, $cnt);
        $stmt->execute();
        $stmt->close();
    }

    MCDelete(SUBSCRIPTION_MESSAGES_CACHEKEY . "lock_$userId");
    if (!$success) {
        DebugMessage("Error adding user message: ".$db->error);
        $db->close();
        return false;
    }

    MCDelete(SUBSCRIPTION_MESSAGES_CACHEKEY . $userId);
    MCDelete(SUBSCRIPTION_MESSAGES_CACHEKEY . $userId . '_' . $seq);

    $stmt = $db->prepare('select name, email from tblUser where id = ? and email is not null and emailverification is null');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $name = '';
    $address = '';
    $stmt->bind_result($name, $address);
    if (!$stmt->fetch()) {
        $address = false;
    }
    $stmt->close();
    if ($address) {
        NewsstandMail($address, $name, $subject, $message);
    }

    $db->close();
    return $seq;
}

function DisableEmailAddress($address) {
    // Note: this is not permanent, a user can re-add it later

    $db = DBConnect(true);
    $stmt = $db->prepare('select id from tblUser where email = ?');
    $stmt->bind_param('s', $address);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = DBMapArray($result);
    $stmt->close();

    $stmt = $db->prepare('update tblUser set email=null, emailverification=null where id = ?');
    $userIdParam = 0;
    $stmt->bind_param('i', $userIdParam);
    foreach ($ids as $userId) {
        $userIdParam = $userId;
        $stmt->execute();
        SendUserMessage($userId, 'Email', 'Email Address Removed', 'We have removed your email address '.htmlspecialchars($address, ENT_COMPAT | ENT_HTML5).' because we received a reply or bounceback from your mail server, or you have chosen to block your address from our system.');
    }
    $stmt->close();

    $db->close();
    return count($ids);
}


<?php
/**
 * Aliyun DDNS Service
 * @author : zhangyunpeng
 * @date   : 2015-12-29
 * @email  : zyp@turbonet.cn
 * @website: blog.turbonet.cn
 * @license: Apache License, Version 2.0
 */

/************************ Config *************************/

$accessKeyId  = 'your access key id';
$accessSecret = 'your access key secret';
$hostRecord   = 'your host record';
$baseDomain   = 'yourdomain.com';
$recordId     = '';

/*********************** End Config **********************/

error_reporting(0);
set_time_limit(120);

$success = true;

do {
    $domainListConfig = array(
        'Action'     => 'DescribeDomainRecords',
        'DomainName' => $baseDomain,
        'RRKeyWord'  => $hostRecord,
    );

    $domainListUrl    = ali_request_url($domainListConfig);
    $domainList       = ssl_request($domainListUrl);
    $domainListObject = json_decode($domainList);

    if (isset($domainListObject->DomainRecords)) {
        $recordId    = $domainListObject->DomainRecords->Record[0]->RecordId;
        $recordValue = $domainListObject->DomainRecords->Record[0]->Value;

        $success = true;
        console_msg('Get the domain name record successfully.');
    } else {
        $success = false;
        console_msg('Failed to get the domain name record, after two seconds retry.');
        sleep(2);
    }

} while(!$success);

do {
    $currentIp = get_ip();

    if ($currentIp == $recordValue) {
        console_msg('Record match, do not modify.');
        break;
    }

    $updateDomainConfig = array(
        'Action'   => 'UpdateDomainRecord',
        'RecordId' => $recordId,
        'RR'       => $hostRecord,
        'Type'     => 'A',
        'Value'    => $currentIp,
    );

    $updateDomainUrl    = ali_request_url($updateDomainConfig);
    $updateDomain       = ssl_request($updateDomainUrl);
    $updateDomainObject = json_decode($updateDomain);

    if (isset($updateDomainObject->RecordId)) {
        $success = true;
        console_msg('Domain name change success.');
    } else {
        $success = false;
        console_msg('Domain name change failed, after two seconds retry.');
        sleep(2);
    }

} while(!$success);

/********************** Functions *********************/

function get_ip()
{
    $result = file_get_contents('http://ddns.oray.com/checkip');

    if (!$result) {
        return false;
    }

    $replace   = array('Current IP Check','Current IP Address',':',' ');
    $ipaddress = str_replace($replace, '', strip_tags($result));

    return ip2long($ipaddress) ? $ipaddress : false;
}

function ssl_request($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL            , $url);
    curl_setopt($ch, CURLOPT_HEADER         , 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST , 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function ali_request_url($currentConfig = array())
{
    global $accessKeyId, $accessSecret;

    date_default_timezone_set('UTC');
    $commonConfig = array(
        'Format'           => 'JSON',
        'Version'          => '2015-01-09',
        'AccessKeyId'      => $accessKeyId,
        'SignatureMethod'  => 'HMAC-SHA1',
        'Timestamp'        => date("Y-m-d\TH:i:s\Z"),
        'SignatureVersion' => '1.0',
        'SignatureNonce'   => time().rand(),
    );

    $config = array_merge($commonConfig, $currentConfig);
    ksort($config);

    $urlParams  = http_build_query($config);
    $signString = 'GET&'.urlencode('/').'&'.urlencode($urlParams);
    $signResult = base64_encode(hash_hmac('sha1', $signString, $accessSecret.'&', true));
    $requestUrl = 'https://dns.aliyuncs.com/?'.$urlParams.'&Signature='.$signResult;

    return $requestUrl;
}

function console_msg($msg)
{
    echo 'Message: '.$msg."\n";
}
?>

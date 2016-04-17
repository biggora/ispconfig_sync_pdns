<?php
/**
 * @revision      $Id$
 * @created       Apr 16, 2016
 * @package       ISPConfig
 * @category      Tools
 * @version       1.0.0
 * @desc          Synchonization tool
 * @copyright     Copyright Alexey Gordeyev IK © 2016 - All rights reserved.
 * @license       MIT
 * @author        Alexey Gordeyev IK <aleksej@gordejev.lv>
 * @link          http://www.gordejev.lv/
 * @source        http://code.google.com/p/ag-php-classes/wiki/ImagesHelper
 */
$srcHost = '';
$srcUser = '';
$srcPass = '';
$srcBase = '';

$dstHost = '';
$dstUser = '';
$dstPass = '';
$dstBase = '';

/**
 * create connection to ispconfig database
 */
$dbsrc = new mysqli($srcHost, $srcUser, $srcPass, $srcBase);
if ($dbsrc->connect_error) {
    die('(ISPConfig) DB Source Connect Error (' . $dbsrc->connect_errno . ') ' . $dbsrc->connect_error);
}

/**
 * create connection to powerdns database
 */
$dbdst = new mysqli($dstHost, $dstUser, $dstPass, $dstBase);
if ($dbdst->connect_error) {
    die('(PowerDNS) DB Destination Connect Error (' . $dbdst->connect_errno . ') ' . $dbdst->connect_error);
}

$domains = array();
$records = array();

/**
 * select SOA records from dns_soa table (ispconfig)
 */
$domains_result = $dbsrc->query('SELECT * FROM `dns_soa` ORDER BY `id`', MYSQLI_USE_RESULT);

if ($domains_result) {
    while ($row = $domains_result->fetch_assoc()) {
        $domain = substr($row['origin'], -1) === '.' ? substr($row['origin'], 0, -1) : $row['origin'];
        $email = substr($row['mbox'], -1) === '.' ? substr($row['mbox'], 0, -1) : $row['mbox'];
        $nameserver = substr($row['ns'], -1) === '.' ? substr($row['ns'], 0, -1) : $row['ns'];
        $domains[$row['id']] = array(
            'id' => $row['id'],
            'name' => $domain,
            'type' => 'MASTER',
            'notified_serial' => $row['serial']
        );
        $records[] = array(
            'domain_id' => $row['id'],
            'name' => $domain,
            'type' => 'SOA',
            'content' => $nameserver . ' ' . $email . ' ' . $row['serial'] . ' ' . $row['refresh'] . ' ' . $row['retry'] . ' ' . $row['expire'] . ' ' . $row['minimum'],
            'ttl' => $row['ttl'],
            'disabled' => ($row['active'] === 'Y' ? 0 : 1),
            'prio' => 0,
            'auth' => 1
        );
        // printf("%s %s\n", $row['id'], $domain);
    }
    $domains_result->free();
}

/**
 * select all records from dns_rr table (ispconfig)
 */
$records_result = $dbsrc->query('SELECT * FROM `dns_rr` ORDER BY `id`', MYSQLI_USE_RESULT);

if ($records_result) {
    while ($row = $records_result->fetch_assoc()) {
        $domain = substr($row['name'], -1) === '.' ? substr($row['name'], 0, -1) : $row['name'];
        $content = substr($row['data'], -1) === '.' ? substr($row['data'], 0, -1) : $row['data'];
        $parent = $domains[$row['zone']];
        if (!preg_match('/' . $parent['name'] . '/i', $row['name'])) {
            $domain .= '.' . $parent['name'];
        }
        $records[] = array(
            'domain_id' => $row['zone'],
            'name' => $domain,
            'type' => $row['type'],
            'content' => $content,
            'ttl' => $row['ttl'],
            'prio' => $row['aux'],
            'disabled' => ($row['active'] === 'Y' ? 0 : 1),
            'auth' => 1
        );
        // printf("%s %s\n", $row['zone'], $domain);
    }
    $records_result->free();
}

$dbdst->begin_transaction();

if (count($domains) > 0) {
    foreach ($domains as &$domain) {
        //
        $dsql = 'INSERT INTO `domains` '
            . ' (`id`, `name`, `type`, `notified_serial`) VALUES '
            . ' (' . $domain['id'] . ',"' . $domain['name'] . '","' . $domain['type'] . '",' . $domain['notified_serial'] . ') '
            . ' ON DUPLICATE KEY UPDATE '
            . ' `name` = "' . $domain['name'] . '",'
            . ' `type` = "' . $domain['type'] . '",'
            . ' `notified_serial` = ' . $domain['notified_serial'] . ';';

        if (!$dbdst->query($dsql)) {
            printf("Insert domain error: %s\n", $dbdst->error);
        }
    }
}

if (count($records) > 0) {
    foreach ($records as &$record) {
        //
        $dsql = 'INSERT INTO `records` '
            . ' (`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `change_date`, `disabled`, `auth`) VALUES '
            . ' (' . $record['domain_id'] . ',"' . $record['name'] . '","' . $record['type']
            . '","' . $record['content'] . '", ' . $record['ttl'] . ', ' . $record['prio']
            . ', NOW(),' . $record['disabled'] . ',' . $record['auth'] . ' ) '
            . ' ON DUPLICATE KEY UPDATE '
            . ' `domain_id` = ' . $record['domain_id'] . ','
            . ' `name` = "' . $record['name'] . '",'
            . ' `type` = "' . $record['type'] . '",'
            . ' `content` = "' . $record['content'] . '",'
            . ' `ttl` = ' . $record['ttl'] . ','
            . ' `prio` = ' . $record['prio'] . ','
            . ' `change_date` = NOW(),'
            . ' `disabled` = ' . $record['disabled'] . ','
            . ' `auth` = ' . $record['auth'] . ';';

        if (!$dbdst->query($dsql)) {
            printf("Insert record error: %s\n", $dbdst->error);
        }
    }
}

// print_r($domains);
// print_r($records);

$dbdst->commit();

$dbsrc->close();
$dbdst->close();
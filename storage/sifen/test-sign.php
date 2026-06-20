<?php

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;
$ns = 'http://ekuatia.set.gov.py/sifen/xsd';
$ds = 'http://www.w3.org/2000/09/xmldsig#';

$rDE = $dom->createElementNS($ns, 'rDE');
$dom->appendChild($rDE);
$rDE->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', $ds);

$de = $dom->createElementNS($ns, 'DE');
$de->setAttribute('Id', '123');
$rDE->appendChild($de);

// Test 1: ds:Signature with unprefixed children
$sig1 = $dom->createElementNS($ds, 'ds:Signature');
$rDE->appendChild($sig1);
$child = $dom->createElementNS($ds, 'SignedInfo');
$sig1->appendChild($child);
$child2 = $dom->createElementNS($ds, 'SignatureValue');
$child2->appendChild($dom->createTextNode('abc'));
$sig1->appendChild($child2);

$xml = $dom->saveXML();
file_put_contents(__DIR__.'/test-sign-out.xml', $xml);

$check = new DOMDocument();
$ok = $check->loadXML($xml);
echo $ok ? "OK loadXML\n" : "FAIL loadXML\n";
echo substr($xml, 0, 500);
echo "\n---TAIL---\n";
echo substr($xml, -150);

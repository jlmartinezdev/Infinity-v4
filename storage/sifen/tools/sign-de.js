#!/usr/bin/env node
/**
 * CLI para firmar DE con facturacionelectronicapy-xmlsign (TIPS).
 * Uso: node sign-de.js <xml_entrada> <cert.p12> <password> [node|java] [xml_salida]
 * Si se indica xml_salida, escribe ahí; si no, usa stdout.
 */
const fs = require('fs');
const path = require('path');
const xmlsign = require('facturacionelectronicapy-xmlsign').default
    || require('facturacionelectronicapy-xmlsign');

const [xmlPath, certPath, password, engine = 'node', outputPath] = process.argv.slice(2);

if (!xmlPath || !certPath || password === undefined) {
    process.stderr.write('Uso: node sign-de.js <xml> <cert.p12> <password> [node|java] [salida.xml]\n');
    process.exit(1);
}

const useNode = engine !== 'java';
const xml = fs.readFileSync(xmlPath, 'utf8');

xmlsign
    .signXML(xml, path.resolve(certPath), password, useNode)
    .then((signed) => {
        if (outputPath) {
            fs.writeFileSync(outputPath, signed, 'utf8');
        } else {
            process.stdout.write(signed);
        }
        process.exit(0);
    })
    .catch((err) => {
        process.stderr.write(String(err?.message || err) + '\n');
        process.exit(1);
    });

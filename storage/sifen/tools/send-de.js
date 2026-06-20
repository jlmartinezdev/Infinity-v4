#!/usr/bin/env node
/**
 * Envío SOAP a SIFEN con mTLS (forge + cadena completa).
 */
const fs = require('fs');
const https = require('https');
const { URL } = require('url');
const { loadPkcs12 } = require('./pkcs12-forge');

const [endpoint, p12Path, password, envelopePath, responsePath, metaPath] = process.argv.slice(2);

if (!endpoint || !p12Path || password === undefined || !envelopePath || !responsePath || !metaPath) {
    process.stderr.write(
        'Uso: node send-de.js <endpoint> <cert.p12> <password> <soap.xml> <respuesta.xml> <meta.json>\n'
    );
    process.exit(1);
}

try {
    const endpointUrl = new URL(endpoint);
    const envelope = fs.readFileSync(envelopePath);
    const material = loadPkcs12(p12Path, password);

    const agent = new https.Agent({
        cert: material.certs,
        key: material.key,
        minVersion: 'TLSv1.2',
        rejectUnauthorized: true,
    });

    const options = {
        hostname: endpointUrl.hostname,
        port: endpointUrl.port || 443,
        path: `${endpointUrl.pathname}${endpointUrl.search}`,
        method: 'POST',
        agent,
        headers: {
            'User-Agent': 'facturaSend',
            'Content-Type': 'application/xml; charset=utf-8',
            Accept: 'application/xml',
            'Content-Length': envelope.length,
        },
    };

    const req = https.request(options, (res) => {
        const chunks = [];
        res.on('data', (chunk) => chunks.push(chunk));
        res.on('end', () => {
            const body = Buffer.concat(chunks).toString('utf8');
            fs.writeFileSync(responsePath, body, 'utf8');
            fs.writeFileSync(
                metaPath,
                JSON.stringify({
                    httpCode: res.statusCode,
                    redirectUrl: res.headers.location || '',
                    cert: material.meta,
                    strategy: 'node-forge-chain',
                }),
                'utf8'
            );
            process.exit(0);
        });
    });

    req.on('error', (err) => {
        fs.writeFileSync(
            metaPath,
            JSON.stringify({
                httpCode: 0,
                redirectUrl: '',
                error: String(err.message || err),
                cert: material.meta,
                strategy: 'node-forge-chain',
            }),
            'utf8'
        );
        process.exit(1);
    });

    req.setTimeout(120000, () => {
        req.destroy(new Error('Timeout de conexión SIFEN (120s)'));
    });

    req.write(envelope);
    req.end();
} catch (err) {
    fs.writeFileSync(
        metaPath,
        JSON.stringify({
            httpCode: 0,
            redirectUrl: '',
            error: String(err.message || err),
            strategy: 'node-forge-chain',
        }),
        'utf8'
    );
    process.exit(1);
}

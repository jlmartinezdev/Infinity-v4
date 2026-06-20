#!/usr/bin/env node
/**
 * Prueba mTLS contra SIFEN (HEAD/POST mínimo).
 * Uso: node probe-tls.js <endpoint> <cert.p12> <password> <meta.json>
 */
const fs = require('fs');
const https = require('https');
const { URL } = require('url');
const { loadPkcs12 } = require('./pkcs12-forge');

const [endpoint, p12Path, password, metaPath] = process.argv.slice(2);

if (!endpoint || !p12Path || password === undefined || !metaPath) {
    process.stderr.write('Uso: node probe-tls.js <endpoint> <cert.p12> <password> <meta.json>\n');
    process.exit(1);
}

try {
    const endpointUrl = new URL(endpoint);
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
            'Content-Type': 'application/soap+xml; charset=UTF-8',
            Accept: 'application/soap+xml',
            'Content-Length': 0,
        },
    };

    const req = https.request(options, (res) => {
        const chunks = [];
        res.on('data', (chunk) => chunks.push(chunk));
        res.on('end', () => {
            const body = Buffer.concat(chunks).toString('utf8');
            fs.writeFileSync(
                metaPath,
                JSON.stringify({
                    ok: res.statusCode !== 302 || !(res.headers.location || '').includes('hangup'),
                    httpCode: res.statusCode,
                    redirectUrl: res.headers.location || '',
                    bodyLength: body.length,
                    cert: material.meta,
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
                ok: false,
                httpCode: 0,
                error: String(err.message || err),
                cert: material.meta,
            }),
            'utf8'
        );
        process.exit(1);
    });

    req.end();
} catch (err) {
    fs.writeFileSync(
        metaPath,
        JSON.stringify({ ok: false, error: String(err.message || err) }),
        'utf8'
    );
    process.exit(1);
}

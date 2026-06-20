#!/usr/bin/env node
/**
 * Envío SOAP a SIFEN — mismo protocolo que facturacionelectronicapy-setapi (TIPS).
 */
const fs = require('fs');
const https = require('https');
const axios = require('axios');
const pkcs12 = require('facturacionelectronicapy-setapi/dist/PKCS12').default
    || require('facturacionelectronicapy-setapi/dist/PKCS12');

const [id, env, certPath, password, xmlPath, responsePath, metaPath, requestPath] = process.argv.slice(2);

if (!id || !env || !certPath || password === undefined || !xmlPath || !responsePath || !metaPath) {
    process.stderr.write(
        'Uso: node send-de-tips.js <dId> <test|prod> <cert.p12> <password> <de.xml> <respuesta.xml> <meta.json> [soap_request.xml]\n'
    );
    process.exit(1);
}

function stripXmlDeclaration(content) {
    let xml = content.replace(/^\uFEFF/, '').trim();
    const lines = xml.split(/\r?\n/);

    // TIPS original: quitar primera línea solo si es <?xml ...?> en archivo multilínea.
    if (lines.length > 1 && /^\s*<\?xml/i.test(lines[0])) {
        return lines.slice(1).join('\n').trim();
    }

    return xml.replace(/^\s*<\?xml[^?]*\?>\s*/i, '').trim();
}

function compactSoap(xml) {
    return xml.replace(/[\r\n\t]/g, '').replace(/>\s+</g, '><');
}

function prepareDeForSoap(content) {
    let xml = stripXmlDeclaration(content);

    if (!/xsi:schemaLocation="/i.test(xml)) {
        throw new Error('El rDE no declara xsi:schemaLocation (requerido por SIFEN).');
    }

    return compactSoap(xml);
}

try {
    pkcs12.openFile(certPath, password);
    const cert = pkcs12.getCertificate();
    const key = pkcs12.getPrivateKey();

    if (!cert || !key) {
        throw new Error('No se pudo extraer certificado o clave del P12.');
    }

    const url = env === 'prod'
        ? 'https://sifen.set.gov.py/de/ws/sync/recibe.wsdl'
        : 'https://sifen-test.set.gov.py/de/ws/sync/recibe.wsdl';

    let xml = prepareDeForSoap(fs.readFileSync(xmlPath, 'utf8'));

    if (xml === '' || !/<rDE\b/i.test(xml)) {
        throw new Error('El archivo DE está vacío o no contiene rDE (revisar XML firmado).');
    }

    if (!/<DE\s+Id="/i.test(xml)) {
        throw new Error('El DE embebido no conserva atributos XML válidos (Id).');
    }

    let soapXMLData = '<?xml version="1.0" encoding="UTF-8"?>'
        + '<env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">'
        + '<env:Header/>'
        + '<env:Body>'
        + '<rEnviDe xmlns="http://ekuatia.set.gov.py/sifen/xsd">'
        + '<dId>' + id + '</dId>'
        + '<xDE>' + xml + '</xDE>'
        + '</rEnviDe>'
        + '</env:Body>'
        + '</env:Envelope>';

    if (requestPath) {
        fs.writeFileSync(requestPath, soapXMLData, 'utf8');
    }

    const httpsAgent = new https.Agent({
        cert: Buffer.from(cert, 'utf8'),
        key: Buffer.from(key, 'utf8'),
        minVersion: 'TLSv1.2',
        rejectUnauthorized: true,
    });

    axios
        .post(url, soapXMLData, {
            headers: {
                'User-Agent': 'facturaSend',
                'Content-Type': 'application/xml; charset=utf-8',
            },
            httpsAgent,
            timeout: 120000,
            validateStatus: () => true,
        })
        .then((resp) => {
            const body = typeof resp.data === 'string' ? resp.data : String(resp.data ?? '');
            fs.writeFileSync(responsePath, body, 'utf8');
            fs.writeFileSync(
                metaPath,
                JSON.stringify({
                    httpCode: resp.status,
                    redirectUrl: '',
                    strategy: 'setapi-tips',
                }),
                'utf8'
            );
            process.exit(0);
        })
        .catch((err) => {
            fs.writeFileSync(
                metaPath,
                JSON.stringify({
                    httpCode: 0,
                    redirectUrl: '',
                    error: String(err.message || err),
                    strategy: 'setapi-tips',
                }),
                'utf8'
            );
            process.exit(1);
        });
} catch (err) {
    fs.writeFileSync(
        metaPath,
        JSON.stringify({
            httpCode: 0,
            redirectUrl: '',
            error: String(err.message || err),
            strategy: 'setapi-tips',
        }),
        'utf8'
    );
    process.exit(1);
}

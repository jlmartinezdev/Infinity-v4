#!/usr/bin/env node
/**
 * Carga P12 con node-forge (igual que la firma TIPS).
 */
const fs = require('fs');
const forge = require('node-forge');

function loadPkcs12(p12Path, password) {
    const raw = fs.readFileSync(p12Path).toString('binary');
    const asn1 = forge.asn1.fromDer(raw);
    const p12 = forge.pkcs12.pkcs12FromAsn1(asn1, password);

    const certBags = p12.getBags({ bagType: forge.pki.oids.certBag })[forge.pki.oids.certBag] || [];
    const keyBags =
        (p12.getBags({ bagType: forge.pki.oids.pkcs8ShroudedKeyBag })[forge.pki.oids.pkcs8ShroudedKeyBag] || [])
            .concat(p12.getBags({ bagType: forge.pki.oids.keyBag })[forge.pki.oids.keyBag] || []);

    if (certBags.length === 0 || keyBags.length === 0 || !keyBags[0].key) {
        throw new Error('El P12 no contiene certificado o clave privada utilizable.');
    }

    const leaf = certBags[0].cert;
    const certs = certBags.map((bag) => forge.pki.certificateToPem(bag.cert));
    const key = forge.pki.privateKeyToPem(keyBags[0].key);

    const extKeyUsage = leaf.getExtension('extKeyUsage');
    const clientAuth = extKeyUsage
        ? (extKeyUsage.clientAuth === true
            || (Array.isArray(extKeyUsage) && extKeyUsage.includes('1.3.6.1.5.5.7.3.2')))
        : false;

    const md = forge.md.sha1.create();
    md.update(forge.asn1.toDer(forge.pki.certificateToAsn1(leaf)).getBytes());
    const sha1 = md.digest().toHex();

    return {
        certs,
        key,
        meta: {
            subject: leaf.subject.getField('CN')?.value || leaf.subject.attributes.map((a) => a.value).join(', '),
            sha1,
            clientAuth,
            certCount: certs.length,
        },
    };
}

module.exports = { loadPkcs12 };

import { createCipheriv, randomBytes } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import { base64ToBytes, BundleError, decryptBundle } from '../src/lib/bundle';

/** Encrypt like Laravel does for CONTRACT §2.3: AES-256-GCM, ciphertext || 16-byte tag. */
function encryptFixture(plaintext: string, key = randomBytes(32), iv = randomBytes(12)) {
    const cipher = createCipheriv('aes-256-gcm', key, iv);
    const body = Buffer.concat([cipher.update(Buffer.from(plaintext, 'utf8')), cipher.final()]);
    const tag = cipher.getAuthTag();
    return {
        version: 3,
        iv: iv.toString('base64'),
        ciphertext: Buffer.concat([body, tag]).toString('base64'),
        key: key.toString('base64'),
    };
}

const PAPER = {
    version: 3,
    questions: [
        {
            id: 5501,
            type: 'multiple',
            section: 'Section A — Science',
            body: 'Which are prime? $\\frac{1}{2}$ — മലയാളം',
            image: null,
            options: [
                { key: 'a', label: '2', image: null },
                { key: 'b', label: '4', image: null },
                { key: 'c', label: '7', image: 'data:image/png;base64,iVBORw0KGgo=' },
            ],
            show_count: 2,
            marks: 2,
            negative_mark: 0.5,
        },
        { id: 5502, type: 'numeric', section: null, body: 'x = ?', image: null, options: [], show_count: null, marks: 1, negative_mark: 0 },
        { id: 5503, type: 'true_false', section: null, body: 'Sky is blue', image: null, options: [{ key: 'a', label: 'True', image: null }, { key: 'b', label: 'False', image: null }], show_count: null, marks: 1, negative_mark: 0 },
    ],
};

describe('paper bundle decryption (WebCrypto AES-GCM)', () => {
    it('decrypts a bundle encrypted with Node crypto', async () => {
        const fx = encryptFixture(JSON.stringify(PAPER));
        const paper = await decryptBundle(fx, fx.key);
        expect(paper.version).toBe(3);
        expect(paper.questions).toHaveLength(3);
        expect(paper.questions[0].body).toContain('മലയാളം');
        expect(paper.questions[0].options[2].image).toBe('data:image/png;base64,iVBORw0KGgo=');
        expect(paper.questions[0].show_count).toBe(2);
        expect(paper.questions[1].type).toBe('numeric');
        expect(paper.questions[1].options).toEqual([]);
    });

    it('rejects a wrong key', async () => {
        const fx = encryptFixture(JSON.stringify(PAPER));
        const wrong = randomBytes(32).toString('base64');
        await expect(decryptBundle(fx, wrong)).rejects.toBeInstanceOf(BundleError);
    });

    it('rejects a tampered ciphertext (GCM tag check)', async () => {
        const fx = encryptFixture(JSON.stringify(PAPER));
        const bytes = base64ToBytes(fx.ciphertext);
        bytes[5] ^= 0x01;
        const tampered = { ...fx, ciphertext: Buffer.from(bytes).toString('base64') };
        await expect(decryptBundle(tampered, fx.key)).rejects.toMatchObject({ reason: 'decrypt' });
    });

    it('rejects a key of the wrong length', async () => {
        const fx = encryptFixture(JSON.stringify(PAPER));
        await expect(decryptBundle(fx, randomBytes(16).toString('base64'))).rejects.toMatchObject({ reason: 'key' });
    });

    it('accepts URL-safe base64 without padding', async () => {
        const fx = encryptFixture(JSON.stringify(PAPER));
        const urlSafe = (s: string) => s.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        const paper = await decryptBundle({ iv: urlSafe(fx.iv), ciphertext: urlSafe(fx.ciphertext) }, urlSafe(fx.key));
        expect(paper.questions).toHaveLength(3);
    });

    it('fixed key/iv vector decrypts to the expected text', async () => {
        const key = Buffer.alloc(32, 7);
        const iv = Buffer.alloc(12, 9);
        const fx = encryptFixture(JSON.stringify({ version: 1, questions: [{ id: 1, type: 'single', body: 'hi', options: [] }] }), key, iv);
        const paper = await decryptBundle(fx, key.toString('base64'));
        expect(paper.questions[0].body).toBe('hi');
    });
});

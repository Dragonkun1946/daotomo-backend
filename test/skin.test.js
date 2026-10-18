const { test, describe, before, after } = require('node:test');
const assert = require('node:assert');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const jwt = require('jsonwebtoken');
const mongoose = require('mongoose');

process.env.JWT_SECRET = process.env.JWT_SECRET || 'test_secret_daotomo_key_2026';

const app = require('../server');
const User = require('../models/User');

let server;
let baseUrl;
const port = 5003;

const testUserId = new mongoose.Types.ObjectId();
const testToken = jwt.sign({ id: testUserId }, process.env.JWT_SECRET);

const mockUser = {
  _id: testUserId,
  username: 'SkinTester',
  email: 'skintester@daotomo.site',
  role: 'user',
  skinUrl: '',
};

// Helper to create a dummy valid PNG buffer with custom width and height
function createMockPngBuffer(width = 64, height = 64) {
  const buf = Buffer.alloc(40);
  // PNG signature
  buf[0] = 0x89; buf[1] = 0x50; buf[2] = 0x4e; buf[3] = 0x47;
  buf[4] = 0x0d; buf[5] = 0x0a; buf[6] = 0x1a; buf[7] = 0x0a;
  // IHDR length
  buf.writeUInt32BE(13, 8);
  // 'IHDR'
  buf.write('IHDR', 12);
  // Width and Height
  buf.writeUInt32BE(width, 16);
  buf.writeUInt32BE(height, 20);
  return buf;
}

before(async () => {
  // Stub User.findById
  User.findById = async (id) => {
    if (id && id.toString() === testUserId.toString()) return mockUser;
    return null;
  };

  // Stub User.findByIdAndUpdate
  User.findByIdAndUpdate = async (id, update) => {
    if (id && id.toString() === testUserId.toString()) {
      if (update.skinUrl !== undefined) mockUser.skinUrl = update.skinUrl;
      return mockUser;
    }
    return null;
  };

  await new Promise((resolve) => {
    server = app.listen(port, () => {
      baseUrl = `http://localhost:${port}`;
      resolve();
    });
  });
});

after(() => {
  if (server) server.close();
});

function request(options, body = null) {
  return new Promise((resolve, reject) => {
    const req = http.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => (data += chunk));
      res.on('end', () => {
        let json = null;
        try { json = JSON.parse(data); } catch (_) { json = data; }
        resolve({ status: res.statusCode, headers: res.headers, body: json });
      });
    });
    req.on('error', reject);
    if (body) {
      if (typeof body === 'object') {
        req.setHeader('Content-Type', 'application/json');
        req.write(JSON.stringify(body));
      } else {
        req.write(body);
      }
    }
    req.end();
  });
}

describe('Minecraft 3D Skin & Steve Default System Tests', () => {
  test('1. Default Steve skin file exists in public/img/steve.png', () => {
    const stevePath = path.join(__dirname, '..', 'public', 'img', 'steve.png');
    assert.strictEqual(fs.existsSync(stevePath), true, 'public/img/steve.png must exist');
    const stats = fs.statSync(stevePath);
    assert.ok(stats.size > 0, 'Steve skin file must not be empty');
  });

  test('2. Unauthenticated POST /api/auth/skin returns 401 Unauthorized', async () => {
    const res = await request({
      hostname: 'localhost',
      port,
      path: '/api/auth/skin',
      method: 'POST',
    }, { skinUrl: 'https://minotar.net/skin/Steve' });

    assert.strictEqual(res.status, 401);
  });

  test('3. POST /api/auth/skin rejects invalid non-PNG file data', async () => {
    const invalidData = 'data:image/jpeg;base64,' + Buffer.from('NOT_A_PNG_DATA_HEADER').toString('base64');
    const res = await request({
      hostname: 'localhost',
      port,
      path: '/api/auth/skin',
      method: 'POST',
      headers: { Authorization: `Bearer ${testToken}` },
    }, { skinData: invalidData });

    assert.strictEqual(res.status, 400);
    assert.ok(res.body.message.includes('PNG'));
  });

  test('4. POST /api/auth/skin rejects invalid Minecraft skin dimensions (e.g. 50x50)', async () => {
    const wrongDimensionBuf = createMockPngBuffer(50, 50);
    const base64 = 'data:image/png;base64,' + wrongDimensionBuf.toString('base64');
    const res = await request({
      hostname: 'localhost',
      port,
      path: '/api/auth/skin',
      method: 'POST',
      headers: { Authorization: `Bearer ${testToken}` },
    }, { skinData: base64 });

    assert.strictEqual(res.status, 400);
    assert.ok(res.body.message.includes('Minecraft') || res.body.message.includes('64x64'));
  });

  test('5. POST /api/auth/skin accepts valid 64x64 PNG and saves to uploads/skins', async () => {
    const validBuf = createMockPngBuffer(64, 64);
    const base64 = 'data:image/png;base64,' + validBuf.toString('base64');
    const res = await request({
      hostname: 'localhost',
      port,
      path: '/api/auth/skin',
      method: 'POST',
      headers: { Authorization: `Bearer ${testToken}` },
    }, { skinData: base64 });

    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.body.success, true);
    assert.ok(res.body.skinUrl.includes(`/uploads/skins/${testUserId}.png`));

    // Verify file exists on disk
    const savedPath = path.join(__dirname, '..', 'public', 'uploads', 'skins', `${testUserId}.png`);
    assert.strictEqual(fs.existsSync(savedPath), true);
  });

  test('6. POST /api/auth/skin with skinUrl: "/img/steve.png" resets skin to default', async () => {
    const res = await request({
      hostname: 'localhost',
      port,
      path: '/api/auth/skin',
      method: 'POST',
      headers: { Authorization: `Bearer ${testToken}` },
    }, { skinUrl: '/img/steve.png' });

    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.body.success, true);
    assert.strictEqual(res.body.skinUrl, '/img/steve.png');
    assert.strictEqual(mockUser.skinUrl, '');
  });

  test('7. POST /api/auth/skin with valid external URL updates skinUrl', async () => {
    const minotarUrl = 'https://minotar.net/skin/Alex';
    const res = await request({
      hostname: 'localhost',
      port,
      path: '/api/auth/skin',
      method: 'POST',
      headers: { Authorization: `Bearer ${testToken}` },
    }, { skinUrl: minotarUrl });

    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.body.success, true);
    assert.strictEqual(res.body.skinUrl, minotarUrl);
    assert.strictEqual(mockUser.skinUrl, minotarUrl);
  });

  test('8. User model schema defaults skinUrl to empty string', () => {
    const userInstance = new User({
      username: 'NewPlayer',
      email: 'player@example.com',
      password: 'strongpassword123',
    });
    assert.strictEqual(userInstance.skinUrl, '');
  });
});

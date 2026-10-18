const { test, describe, before, after, beforeEach } = require('node:test');
const assert = require('node:assert');
const http = require('node:http');
const jwt = require('jsonwebtoken');
const mongoose = require('mongoose');

// Ensure JWT_SECRET is set for tests
process.env.JWT_SECRET = process.env.JWT_SECRET || 'test_secret_daotomo_key_2026';

const app = require('../server');
const Order = require('../models/Order');
const Product = require('../models/Product');
const User = require('../models/User');
const orderController = require('../controllers/orderController');

let server;
let baseUrl;
const port = 5002;

// Fake IDs
const userAId = new mongoose.Types.ObjectId();
const userBId = new mongoose.Types.ObjectId();
const adminId = new mongoose.Types.ObjectId();
const testProductId = new mongoose.Types.ObjectId();

// Generate tokens
const userAToken = jwt.sign({ id: userAId }, process.env.JWT_SECRET);
const userBToken = jwt.sign({ id: userBId }, process.env.JWT_SECRET);
const adminToken = jwt.sign({ id: adminId }, process.env.JWT_SECRET);

// Mock Users in memory for protect middleware
const mockUsers = {
  [userAId.toString()]: { _id: userAId, username: 'userA', email: 'usera@example.com', role: 'user', minecraftUsername: 'SteveA' },
  [userBId.toString()]: { _id: userBId, username: 'userB', email: 'userb@example.com', role: 'user', minecraftUsername: 'SteveB' },
  [adminId.toString()]: { _id: adminId, username: 'adminUser', email: 'admin@example.com', role: 'admin' },
};

before(async () => {
  // Stub User.findById
  User.findById = async (id) => {
    const idStr = id ? id.toString() : '';
    return mockUsers[idStr] || null;
  };

  await new Promise((resolve) => {
    server = app.listen(port, () => {
      baseUrl = `http://localhost:${port}`;
      resolve();
    });
  });
});

after(async () => {
  await new Promise((resolve) => server.close(resolve));
});

describe('1. Unauthenticated & Protected Route Checks', () => {
  test('POST /api/orders without token returns 401 Unauthorized', async () => {
    const res = await fetch(`${baseUrl}/api/orders`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: [{ productId: testProductId, quantity: 1 }] }),
    });
    const data = await res.json();
    assert.strictEqual(res.status, 401);
    assert.strictEqual(data.success, false);
    assert.match(data.message, /chưa đăng nhập/i);
  });

  test('GET /api/orders/my without token returns 401 Unauthorized', async () => {
    const res = await fetch(`${baseUrl}/api/orders/my`);
    const data = await res.json();
    assert.strictEqual(res.status, 401);
    assert.strictEqual(data.success, false);
  });

  test('GET /api/orders/:id without token returns 401 Unauthorized', async () => {
    const res = await fetch(`${baseUrl}/api/orders/${new mongoose.Types.ObjectId()}`);
    const data = await res.json();
    assert.strictEqual(res.status, 401);
    assert.strictEqual(data.success, false);
  });

  test('GET /api/orders (admin) without token returns 401 Unauthorized', async () => {
    const res = await fetch(`${baseUrl}/api/orders`);
    const data = await res.json();
    assert.strictEqual(res.status, 401);
    assert.strictEqual(data.success, false);
  });
});

describe('2. Role-Based Access Control (Admin vs Regular User)', () => {
  test('Regular user accessing admin orders list GET /api/orders returns 403 Forbidden', async () => {
    const res = await fetch(`${baseUrl}/api/orders`, {
      headers: { Authorization: `Bearer ${userAToken}` },
    });
    const data = await res.json();
    assert.strictEqual(res.status, 403);
    assert.strictEqual(data.success, false);
    assert.match(data.message, /không có quyền/i);
  });

  test('Regular user calling PUT /api/orders/:id/status returns 403 Forbidden', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const res = await fetch(`${baseUrl}/api/orders/${orderId}/status`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${userAToken}`,
      },
      body: JSON.stringify({ status: 'paid' }),
    });
    const data = await res.json();
    assert.strictEqual(res.status, 403);
    assert.strictEqual(data.success, false);
  });
});

describe('3. Cross-Account Order Access Prevention', () => {
  test('User B cannot view User A order (returns 403 Forbidden)', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      user: { _id: userAId, username: 'userA', email: 'usera@example.com' },
      items: [{ name: 'Hòm VIP', price: 50000, quantity: 1 }],
      total: 50000,
      orderStatus: 'pending',
      paymentStatus: 'pending',
    };

    const originalFindOne = Order.findOne;
    Order.findOne = () => ({
      populate: () => ({
        populate: async () => fakeOrder,
      }),
    });

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}`, {
        headers: { Authorization: `Bearer ${userBToken}` },
      });
      const data = await res.json();
      assert.strictEqual(res.status, 403);
      assert.strictEqual(data.success, false);
      assert.match(data.message, /không có quyền/i);
    } finally {
      Order.findOne = originalFindOne;
    }
  });

  test('Owner (User A) CAN view User A order (returns 200 OK)', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      user: { _id: userAId, username: 'userA', email: 'usera@example.com' },
      items: [{ name: 'Hòm VIP', price: 50000, quantity: 1 }],
      total: 50000,
      orderStatus: 'pending',
      paymentStatus: 'pending',
    };

    const originalFindOne = Order.findOne;
    Order.findOne = () => ({
      populate: () => ({
        populate: async () => fakeOrder,
      }),
    });

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}`, {
        headers: { Authorization: `Bearer ${userAToken}` },
      });
      const data = await res.json();
      assert.strictEqual(res.status, 200);
      assert.strictEqual(data.success, true);
      assert.strictEqual(data.data.total, 50000);
    } finally {
      Order.findOne = originalFindOne;
    }
  });

  test('Admin CAN view User A order (returns 200 OK)', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      user: { _id: userAId, username: 'userA', email: 'usera@example.com' },
      items: [{ name: 'Hòm VIP', price: 50000, quantity: 1 }],
      total: 50000,
      orderStatus: 'pending',
      paymentStatus: 'pending',
    };

    const originalFindOne = Order.findOne;
    Order.findOne = () => ({
      populate: () => ({
        populate: async () => fakeOrder,
      }),
    });

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}`, {
        headers: { Authorization: `Bearer ${adminToken}` },
      });
      const data = await res.json();
      assert.strictEqual(res.status, 200);
      assert.strictEqual(data.success, true);
    } finally {
      Order.findOne = originalFindOne;
    }
  });
});

describe('4. Input Validation & Pricing Integrity (createOrder)', () => {
  test('Reject empty items array', async () => {
    const res = await fetch(`${baseUrl}/api/orders`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${userAToken}`,
      },
      body: JSON.stringify({ items: [] }),
    });
    const data = await res.json();
    assert.strictEqual(res.status, 400);
    assert.strictEqual(data.success, false);
  });

  test('Reject invalid ObjectId in items', async () => {
    const res = await fetch(`${baseUrl}/api/orders`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${userAToken}`,
      },
      body: JSON.stringify({ items: [{ productId: 'invalid-id-123', quantity: 1 }] }),
    });
    const data = await res.json();
    assert.strictEqual(res.status, 400);
    assert.strictEqual(data.success, false);
    assert.match(data.message, /không hợp lệ/i);
  });

  test('Reject zero quantity (quantity: 0)', async () => {
    const res = await fetch(`${baseUrl}/api/orders`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${userAToken}`,
      },
      body: JSON.stringify({ items: [{ productId: testProductId, quantity: 0 }] }),
    });
    const data = await res.json();
    assert.strictEqual(res.status, 400);
    assert.strictEqual(data.success, false);
    assert.match(data.message, /lớn hơn 0/i);
  });

  test('Reject negative quantity (quantity: -3)', async () => {
    const res = await fetch(`${baseUrl}/api/orders`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${userAToken}`,
      },
      body: JSON.stringify({ items: [{ productId: testProductId, quantity: -3 }] }),
    });
    const data = await res.json();
    assert.strictEqual(res.status, 400);
    assert.strictEqual(data.success, false);
    assert.match(data.message, /lớn hơn 0/i);
  });

  test('Reject float quantity (quantity: 2.5)', async () => {
    const res = await fetch(`${baseUrl}/api/orders`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${userAToken}`,
      },
      body: JSON.stringify({ items: [{ productId: testProductId, quantity: 2.5 }] }),
    });
    const data = await res.json();
    assert.strictEqual(res.status, 400);
    assert.strictEqual(data.success, false);
    assert.match(data.message, /số nguyên dương/i);
  });

  test('Reject non-existent product (404)', async () => {
    const originalFindById = Product.findById;
    Product.findById = async () => null;

    try {
      const res = await fetch(`${baseUrl}/api/orders`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${userAToken}`,
        },
        body: JSON.stringify({ items: [{ productId: testProductId, quantity: 1 }] }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 404);
      assert.strictEqual(data.success, false);
      assert.match(data.message, /không tồn tại/i);
    } finally {
      Product.findById = originalFindById;
    }
  });

  test('Reject inactive product', async () => {
    const originalFindById = Product.findById;
    Product.findById = async () => ({
      _id: testProductId,
      name: 'Item Tắt Bán',
      status: 'inactive',
      price: 10000,
      inStock: true,
    });

    try {
      const res = await fetch(`${baseUrl}/api/orders`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${userAToken}`,
        },
        body: JSON.stringify({ items: [{ productId: testProductId, quantity: 1 }] }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 400);
      assert.strictEqual(data.success, false);
      assert.match(data.message, /không còn mở bán/i);
    } finally {
      Product.findById = originalFindById;
    }
  });

  test('Reject out of stock product', async () => {
    const originalFindById = Product.findById;
    Product.findById = async () => ({
      _id: testProductId,
      name: 'Item Hết Hàng',
      status: 'active',
      price: 10000,
      inStock: false,
    });

    try {
      const res = await fetch(`${baseUrl}/api/orders`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${userAToken}`,
        },
        body: JSON.stringify({ items: [{ productId: testProductId, quantity: 1 }] }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 400);
      assert.strictEqual(data.success, false);
      assert.match(data.message, /đã hết hàng/i);
    } finally {
      Product.findById = originalFindById;
    }
  });

  test('Backend ignores fake client price and computes DB price correctly', async () => {
    const originalProductFindById = Product.findById;
    const originalOrderCreate = Order.create;
    const originalOrderFindById = Order.findById;

    const realPrice = 85000;
    const qty = 3;
    const expectedTotal = realPrice * qty; // 255000

    Product.findById = async () => ({
      _id: testProductId,
      name: 'Vip Bundle',
      status: 'active',
      price: realPrice,
      inStock: true,
      stock: 10,
      save: async () => {},
    });

    let createdOrderPayload = null;
    Order.create = async (payload) => {
      createdOrderPayload = payload;
      return { _id: new mongoose.Types.ObjectId(), ...payload };
    };

    Order.findById = () => ({
      populate: () => ({
        populate: async () => ({
          _id: new mongoose.Types.ObjectId(),
          orderCode: 'DTMTEST001',
          total: expectedTotal,
          items: createdOrderPayload.items,
        }),
      }),
    });

    try {
      // Client maliciously sends fake price 1đ and fake total 3đ
      const res = await fetch(`${baseUrl}/api/orders`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${userAToken}`,
        },
        body: JSON.stringify({
          items: [{ productId: testProductId, quantity: qty, price: 1 }],
          total: 3,
        }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 201);
      assert.strictEqual(createdOrderPayload.total, expectedTotal);
      assert.strictEqual(createdOrderPayload.items[0].price, realPrice);
    } finally {
      Product.findById = originalProductFindById;
      Order.create = originalOrderCreate;
      Order.findById = originalOrderFindById;
    }
  });
});

describe('5. State Machine & Admin Status Transitions', () => {
  test('Admin can update orderStatus from pending to processing', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      orderStatus: 'pending',
      paymentStatus: 'pending',
      items: [],
      save: async () => {},
    };

    const origFindById = Order.findById;
    Order.findById = (id) => {
      if (id.toString() === orderId.toString()) {
        return {
          ...fakeOrder,
          populate: () => ({
            populate: async () => ({ ...fakeOrder, orderStatus: 'processing' }),
          }),
        };
      }
      return null;
    };

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}/status`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({ orderStatus: 'processing' }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 200);
      assert.strictEqual(data.success, true);
    } finally {
      Order.findById = origFindById;
    }
  });

  test('Admin CANNOT transition cancelled order to paid (Invariance rule)', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      orderStatus: 'cancelled',
      paymentStatus: 'pending',
      items: [],
      save: async () => {},
    };

    const origFindById = Order.findById;
    Order.findById = async () => fakeOrder;

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}/status`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({ paymentStatus: 'paid' }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 400);
      assert.strictEqual(data.success, false);
      assert.match(data.message, /đã huỷ không thể/i);
    } finally {
      Order.findById = origFindById;
    }
  });

  test('Admin CANNOT transition delivered order back to pending', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      orderStatus: 'delivered',
      paymentStatus: 'paid',
      items: [],
      save: async () => {},
    };

    const origFindById = Order.findById;
    Order.findById = async () => fakeOrder;

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}/status`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({ orderStatus: 'pending' }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 400);
      assert.strictEqual(data.success, false);
      assert.match(data.message, /không thể chuyển trạng thái/i);
    } finally {
      Order.findById = origFindById;
    }
  });
});

describe('6. Order Cancellation & Payment Proof', () => {
  test('User can cancel own pending order', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      user: userAId,
      orderStatus: 'pending',
      paymentStatus: 'pending',
      items: [],
      save: async () => {},
    };

    const origFindById = Order.findById;
    Order.findById = async () => fakeOrder;

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}/cancel`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${userAToken}`,
        },
        body: JSON.stringify({ reason: 'Đổi ý không mua nữa' }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 200);
      assert.strictEqual(data.success, true);
      assert.strictEqual(fakeOrder.orderStatus, 'cancelled');
    } finally {
      Order.findById = origFindById;
    }
  });

  test('User CANNOT cancel already delivered order', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      user: userAId,
      orderStatus: 'delivered',
      paymentStatus: 'paid',
      items: [],
      save: async () => {},
    };

    const origFindById = Order.findById;
    Order.findById = async () => fakeOrder;

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}/cancel`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${userAToken}`,
        },
      });
      const data = await res.json();
      assert.strictEqual(res.status, 400);
      assert.strictEqual(data.success, false);
      assert.match(data.message, /không thể huỷ/i);
    } finally {
      Order.findById = origFindById;
    }
  });

  test('User submit payment proof saves proof but keeps paymentStatus pending', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      user: userAId,
      orderStatus: 'pending',
      paymentStatus: 'pending',
      paymentProof: '',
      save: async () => {},
    };

    const origFindById = Order.findById;
    Order.findById = async () => fakeOrder;

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}/payment-proof`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${userAToken}`,
        },
        body: JSON.stringify({ paymentProof: 'https://example.com/proof123.jpg' }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 200);
      assert.strictEqual(data.success, true);
      assert.strictEqual(fakeOrder.paymentProof, 'https://example.com/proof123.jpg');
      assert.strictEqual(fakeOrder.paymentStatus, 'pending'); // Critical: MUST NOT BE PAID
    } finally {
      Order.findById = origFindById;
    }
  });
});

describe('7. Idempotency & Duplicate Request Protection', () => {
  test('Sending request with same X-Idempotency-Key returns existing order without creating duplicate', async () => {
    const origFindOne = Order.findOne;
    const existingOrderId = new mongoose.Types.ObjectId();
    const existingOrder = {
      _id: existingOrderId,
      orderCode: 'DTM-IDEMP-001',
      total: 99000,
      user: userAId,
      idempotencyKey: 'client-key-xyz-123',
    };

    Order.findOne = (query) => {
      if (query.idempotencyKey === 'client-key-xyz-123') {
        return {
          populate: async () => existingOrder,
        };
      }
      return null;
    };

    try {
      const res = await fetch(`${baseUrl}/api/orders`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${userAToken}`,
          'X-Idempotency-Key': 'client-key-xyz-123',
        },
        body: JSON.stringify({
          items: [{ productId: testProductId, quantity: 1 }],
          idempotencyKey: 'client-key-xyz-123',
        }),
      });

      const data = await res.json();
      assert.strictEqual(res.status, 200);
      assert.strictEqual(data.success, true);
      assert.strictEqual(data.data.orderCode, 'DTM-IDEMP-001');
      assert.match(data.message, /idempotent/i);
    } finally {
      Order.findOne = origFindOne;
    }
  });
});

describe('8. Legacy Status Field Backward Compatibility', () => {
  test('Admin sending legacy status: "paid" updates paymentStatus to paid and keeps legacy status in sync', async () => {
    const orderId = new mongoose.Types.ObjectId();
    const fakeOrder = {
      _id: orderId,
      orderStatus: 'pending',
      paymentStatus: 'pending',
      status: 'pending',
      items: [],
      paymentDetails: {},
      save: async function () {
        this.status = this.paymentStatus === 'paid' ? 'paid' : this.status;
      },
    };

    const origFindById = Order.findById;
    Order.findById = (id) => {
      fakeOrder.populate = () => ({
        populate: async () => fakeOrder,
      });
      return fakeOrder;
    };

    try {
      const res = await fetch(`${baseUrl}/api/orders/${orderId}/status`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${adminToken}`,
        },
        body: JSON.stringify({ status: 'paid' }),
      });
      const data = await res.json();
      assert.strictEqual(res.status, 200);
      assert.strictEqual(data.success, true);
      assert.strictEqual(fakeOrder.paymentStatus, 'paid');
    } finally {
      Order.findById = origFindById;
    }
  });
});

describe('9. Order Schema Unit Tests (Hooks & Code Generation)', () => {
  test('New order document automatically generates unique orderCode and transferContent', () => {
    const doc = new Order({
      user: userAId,
      items: [{ product: testProductId, name: 'VIP Pass', price: 50000, quantity: 1 }],
      total: 50000,
    });

    doc.syncStatuses();

    assert.ok(doc.orderCode, 'orderCode should be generated');
    assert.match(doc.orderCode, /^DTM/);
    assert.strictEqual(doc.paymentDetails.transferContent, doc.orderCode);
    assert.strictEqual(doc.status, 'pending');
    assert.strictEqual(doc.orderStatus, 'pending');
    assert.strictEqual(doc.paymentStatus, 'pending');
  });

  test('Pre-save hook syncs status field when paymentStatus is paid', () => {
    const doc = new Order({
      user: userAId,
      items: [{ product: testProductId, name: 'VIP Pass', price: 50000, quantity: 1 }],
      total: 50000,
      orderStatus: 'processing',
      paymentStatus: 'paid',
    });

    doc.syncStatuses();

    assert.strictEqual(doc.status, 'paid');
  });

  test('Pre-save hook syncs status field when orderStatus is cancelled', () => {
    const doc = new Order({
      user: userAId,
      items: [{ product: testProductId, name: 'VIP Pass', price: 50000, quantity: 1 }],
      total: 50000,
      orderStatus: 'cancelled',
      paymentStatus: 'failed',
    });

    doc.syncStatuses();

    assert.strictEqual(doc.status, 'cancelled');
  });
});

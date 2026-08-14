const DATABASE = "opto-sync-laravel-livewire";
const STORE = "mutations";
const TAG = "opto-sync-laravel-multiplex";

function openDatabase() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DATABASE, 1);
    request.onupgradeneeded = () => {
      const store = request.result.createObjectStore(STORE, { keyPath: "id" });
      store.createIndex("createdAt", "createdAt");
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function transact(mode, operation) {
  const database = await openDatabase();
  return new Promise((resolve, reject) => {
    const transaction = database.transaction(STORE, mode);
    const result = operation(transaction.objectStore(STORE));
    transaction.oncomplete = () => {
      database.close();
      resolve(result instanceof IDBRequest ? result.result : result);
    };
    transaction.onerror = () => reject(transaction.error);
    transaction.onabort = () => reject(transaction.error);
  });
}

async function enqueue(input) {
  const durable = Object.freeze({
    ...input,
    id: input.id ?? crypto.randomUUID(),
    createdAt: input.createdAt ?? Date.now(),
  });
  await transact("readwrite", (store) => store.put(durable));
  await self.registration.sync?.register(TAG);
}

async function send(lane, body, sha256) {
  const response = await fetch(`/api/sync/${lane}`, {
    method: "POST",
    headers: {
      "content-type": "application/json",
      "x-opto-sync-lane": lane,
      "x-opto-sync-batch-sha256": sha256,
    },
    body,
  });
  if (!response.ok) throw new Error(`${lane} lane failed with ${response.status}`);
  return response.json();
}

async function digest(body) {
  const bytes = new TextEncoder().encode(body);
  const hash = await crypto.subtle.digest("SHA-256", bytes);
  return [...new Uint8Array(hash)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function flush() {
  const rows = await transact("readonly", (store) => store.getAll());
  if (rows.length === 0) return;

  const snapshot = Object.freeze(
    rows
      .sort((left, right) => left.createdAt - right.createdAt)
      .map((row) => Object.freeze({ ...row })),
  );
  const body = JSON.stringify(snapshot);
  const sha256 = await digest(body);
  const outcomes = await Promise.allSettled([
    send("upload", body, sha256),
    send("realtime", body, sha256),
  ]);
  if (outcomes.some((outcome) => outcome.status === "rejected")) {
    throw new Error("partial multiplex delivery; exact durable batch retained");
  }

  await transact("readwrite", (store) => {
    for (const mutation of snapshot) store.delete(mutation.id);
  });
}

self.addEventListener("install", (event) => event.waitUntil(self.skipWaiting()));
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));
self.addEventListener("message", (event) => {
  if (event.data?.type === "OPTO_SYNC_ENQUEUE") event.waitUntil(enqueue(event.data.mutation));
  if (event.data?.type === "OPTO_SYNC_WAKE") event.waitUntil(flush());
});
self.addEventListener("sync", (event) => {
  if (event.tag === TAG) event.waitUntil(flush());
});
self.addEventListener("periodicsync", (event) => {
  if (event.tag === TAG) event.waitUntil(flush());
});


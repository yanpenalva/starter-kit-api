// Run with the official schema path and an installed AJV 2020 module path.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const Ajv = require(path.resolve(process.argv[3]));
const root = path.resolve(__dirname, '../..');
const read = name => JSON.parse(fs.readFileSync(path.join(root, name), 'utf8'));
const collection = read('docs/insomnia/starter-kit-api-insomnia.json');
const ajv = new Ajv({strict: false, allErrors: true});
const validate = ajv.compile(JSON.parse(fs.readFileSync(process.argv[2], 'utf8')));
assert(validate(collection), JSON.stringify(validate.errors));
const requests = collection.collection.flatMap(folder => folder.children);
const resources = collection.resources.filter(resource => resource._type === 'request');
assert.equal(requests.length, 37);
assert.equal(resources.length, 37);
assert.equal(new Set(collection.resources.map(resource => resource._id)).size, collection.resources.length);
const resourceIds = new Set(collection.resources.map(resource => resource._id));
for (const resource of collection.resources) {
  if (resource.parentId) assert(resourceIds.has(resource.parentId), resource._id);
}
const keys = request => {
  const match = request.meta.description.match(/^Rota: (\w+) (\S+)/);
  assert(match, request.name);
  const variables = {base_url: '', api_prefix: '/api/v1', user_id: '{user}',
    role_id: '{role}', session_id: '{id}', verification_id: '{id}', verification_hash: '{hash}'};
  const uri = request.url.replace(/\{\{\s*_\.(\w+)\s*\}\}/g, (_, name) => {
    assert(Object.hasOwn(variables, name), `Unknown path variable: ${name}`);
    return variables[name];
  }).replace(/\/$/, '');
  const key = `${request.method} ${uri}`;
  assert.equal(key, `${match[1]} ${match[2].replace(/\/$/, '')}`, request.name);
  return key;
};
for (const request of requests) {
  const mirror = resources.find(resource => resource._id === request.meta.id);
  assert(mirror, request.name);
  for (const field of ['name', 'method', 'url', 'headers', 'parameters', 'body', 'authentication']) {
    assert.deepEqual(request[field], mirror[field], `${request.name}: ${field}`);
  }
  assert.equal(request.meta.description, mirror.description, request.name);
  assert.equal(request.settings.cookies.send, mirror.settingSendCookies);
  assert.equal(request.settings.cookies.store, mirror.settingStoreCookies);
  assert.equal(request.settings.followRedirects, mirror.settingFollowRedirects);
  assert.deepEqual(request.authentication, {}, request.name);
  assert(!request.headers.some(header => header.name.toLowerCase() === 'authorization'));
  if (request.body.mimeType === 'application/json') JSON.parse(request.body.text);
  for (const example of request.meta.description.matchAll(/```json\n([\s\S]*?)\n```/g)) JSON.parse(example[1]);
}
const routes = read('docs/insomnia/live-routes.json');
const apiRoutes = new Set(routes.filter(route => route.uri.startsWith('api/v1')).map(route =>
  `${route.method.split('|')[0]} /${route.uri.replace(/\/$/, '')}`));
const requestKeys = new Set(requests.map(keys));
const apiRequests = new Set([...requestKeys].filter(key => key.includes(' /api/v1')));
assert.equal(apiRoutes.size, 35);
assert.deepEqual(apiRequests, apiRoutes);
const captures = read('docs/insomnia/live-responses.json');
const observed = new Set(captures.map(record => `${record.method} ${record.route.replace(/\/$/, '')}`));
for (const key of requestKeys) assert(observed.has(key), `Missing live capture: ${key}`);
assert.equal(requestKeys.size, 37);
assert(captures.every(record => Object.hasOwn(record, 'body') && Number.isInteger(record.status)));
console.log('PASS: Insomnia v5.1 schema, 37 mirrored requests, JSON bodies/examples, 35/35 API route coverage and 37/37 live captures');

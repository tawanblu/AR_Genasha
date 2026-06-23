const fs = require('fs');
const path = require('path');
const { decode, encode } = require('./msgpack.min.js');

const MIND_DIR = path.join(__dirname, '..', 'mind_files');

function shrink(inName, outName, maxTargets) {
  const inPath = path.join(MIND_DIR, inName);
  const outPath = path.join(MIND_DIR, outName);
  const data = decode(fs.readFileSync(inPath));
  if (!data.dataList || !Array.isArray(data.dataList)) {
    throw new Error(`${inName}: invalid .mind format`);
  }
  const count = Math.min(maxTargets, data.dataList.length);
  const out = encode({ ...data, dataList: data.dataList.slice(0, count) });
  fs.writeFileSync(outPath, Buffer.from(out));
  const mb = (fs.statSync(outPath).size / 1048576).toFixed(2);
  return { outName, mb, count };
}

const zones = ['silpakorn', 'watnayang'];
const targetCounts = [3, 4, 5, 6, 7, 8, 9];

console.log('Original sizes:');
for (const z of zones) {
  const p = path.join(MIND_DIR, `${z}.mind`);
  const data = decode(fs.readFileSync(p));
  console.log(`  ${z}.mind: ${(fs.statSync(p).size / 1048576).toFixed(2)} MB, ${data.dataList.length} targets`);
}

console.log('\nShrunk variants:');
for (const z of zones) {
  for (const n of targetCounts) {
    const r = shrink(`${z}.mind`, `${z}_${n}t.mind`, n);
    console.log(`  ${r.outName}: ${r.mb} MB (${r.count} targets)`);
  }
}

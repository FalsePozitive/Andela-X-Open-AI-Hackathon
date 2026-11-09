const fs=require('fs');const {PNG}=require('pngjs');const jsQR=require('jsqr');
const file=process.argv[2]||'tests/.runtime/exports/marked-image.png';
const png=PNG.sync.read(fs.readFileSync(file));const result=jsQR(new Uint8ClampedArray(png.data),png.width,png.height);
if(result?.data!=='https://okwethu.wilmans.co.za/p/ABCD-EFGH')throw new Error('The saved media does not contain the expected readable QR');
console.log('Decoded passport QR from saved media: '+result.data);

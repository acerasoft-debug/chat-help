#!/bin/bash
# Baut die Ordnerstruktur wie auf dem Server: /chat/voice (Repo) + /chat/vendor/voice/{lib,models} (npm + Hugging Face)
set -e
R=/tmp/site; mkdir -p $R/chat/vendor/voice/lib $R/chat/vendor/voice/models
cp -r chat/voice $R/chat/voice
cat > $R/test.html <<'H'
<!doctype html><meta charset="utf-8"><title>Stimmen-Test</title><script src="/chat/voice/voice.js"></script>
H
cd /tmp && npm pack -q onnxruntime-web@1.20.1 @diffusionstudio/piper-wasm@1.0.0 >/dev/null && mkdir -p o w && tar xzf onnxruntime-web-1.20.1.tgz -C o && tar xzf diffusionstudio-piper-wasm-1.0.0.tgz -C w
L=$R/chat/vendor/voice/lib
cp o/package/dist/ort.wasm.min.js o/package/dist/ort-wasm-simd-threaded.mjs o/package/dist/ort-wasm-simd-threaded.wasm $L/
cp w/package/build/piper_phonemize.js w/package/build/piper_phonemize.wasm $L/ && cp w/package/build/piper_phonemize.data $L/piper_phonemize.data.bin
sha256sum $L/*
M=$R/chat/vendor/voice/models; HF=https://huggingface.co/rhasspy/piper-voices/resolve/main; DS=https://huggingface.co/diffusionstudio/piper-voices/resolve/main
get() { curl -sSfL "$2" -o "$M/$1.bin"; curl -sSfL "$2.json" -o "$M/$1.json"; }
get de_DE-kerstin-low $HF/de/de_DE/kerstin/low/de_DE-kerstin-low.onnx
get de_DE-mls-medium $HF/de/de_DE/mls/medium/de_DE-mls-medium.onnx
get fr_FR-upmc-medium $HF/fr/fr_FR/upmc/medium/fr_FR-upmc-medium.onnx
get es_ES-sharvard-medium $HF/es/es_ES/sharvard/medium/es_ES-sharvard-medium.onnx
get tr_TR-fahrettin-medium $DS/tr/tr_TR/fahrettin/medium/tr_TR-fahrettin-medium.onnx
ls -la $M

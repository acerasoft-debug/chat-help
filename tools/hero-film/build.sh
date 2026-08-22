#!/usr/bin/env bash
# Cut the homepage hero film from the catalogue's own packshots.
#
#   bash tools/hero-film/build.sh
#
# Writes vestra/assets/hero/{hero.mp4,hero.webm,hero-poster.jpg}. index.php switches
# itself on when hero.mp4 is present and falls back to the CSS plate strip when it is
# not, so removing the three files is a complete revert.
#
# Needs: node with playwright (NODE_PATH), ffmpeg with libx264 + libvpx-vp9.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK="${TMPDIR:-/tmp}/vestra-hero-film"
DEST="$ROOT/vestra/assets/hero"
FPS=24
FRAMES=480            # 480 / 24 = a 20s loop

export NODE_PATH="${NODE_PATH:-/opt/node22/lib/node_modules}"

rm -rf "$WORK"; mkdir -p "$WORK/frames" "$DEST"

echo "== rendering =="
node "$ROOT/tools/hero-film/render.js" "$WORK/frames" "$FRAMES"

# H.264 for reach -- it is the only format Safari and iOS will take.
echo "== h.264 =="
ffmpeg -nostdin -v error -y -framerate "$FPS" -i "$WORK/frames/%04d.jpg" \
  -c:v libx264 -profile:v high -level 4.0 -pix_fmt yuv420p -crf 25 -preset slow \
  -movflags +faststart -an "$DEST/hero.mp4"

# VP9 for everything else: about half the bytes at the same quality, and Chrome and
# Firefox are most of the traffic. index.php picks this one when canPlayType says yes.
echo "== vp9 =="
ffmpeg -nostdin -v error -y -framerate "$FPS" -i "$WORK/frames/%04d.jpg" \
  -c:v libvpx-vp9 -crf 34 -b:v 0 -row-mt 1 -cpu-used 3 -pix_fmt yuv420p -an \
  "$DEST/hero.webm"

# Frame 0 is the poster. It is also the whole hero on phones, on cellular, and for
# anyone who asked for reduced motion -- those never fetch the clip at all.
echo "== poster =="
ffmpeg -nostdin -v error -y -i "$WORK/frames/0000.jpg" -q:v 4 "$DEST/hero-poster.jpg"

rm -rf "$WORK"
ls -la "$DEST"

#!/bin/sh
# Butun testleri kosar, kalanlarda 1 doner. CI'ya baglamak icin: sh tests/run_all.sh
fail=0
for f in "$(dirname "$0")"/*_test.php; do
  printf '%-36s ' "$(basename "$f")"
  if out=$(php "$f" 2>&1); then echo "gecti"; else echo "KALDI"; echo "$out" | tail -8 | sed 's/^/    /'; fail=1; fi
done
[ "$fail" -eq 0 ] && echo "--- hepsi gecti ---" || echo "--- BAZI TESTLER KALDI ---"
exit $fail

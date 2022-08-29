#!/bin/bash

flatc_binary=/data/home/web/_redive/cron/dankagu/flatc/flatc
schema_dir=/data/home/web/_redive/cron/dankagu/schema

for a in ../*.raw; do

fname=${a#../}
fname=${fname%.raw}
name=${fname%-*}
echo $fname
$flatc_binary --json $schema_dir/${name}Raw.fbs -- ../$fname.raw

done

sed -i -E -e 's/  (\w*):/  "\1":/g' *.json
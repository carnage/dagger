#!/bin/sh

cd $1

if ! [ -f composer.json ]; then
  cp -r /codegen/template/* .
fi;

sed -i 's/class Example/class $2/g' ./src/Example.php

mv ./src/Example.php ./src/$2.php
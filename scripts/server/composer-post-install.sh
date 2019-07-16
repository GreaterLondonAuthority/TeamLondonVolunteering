#!/bin/bash
# 22/01/2018 by user2 & user2 - initial Composer build

themename=$1
webroot=$2

echo Running post deploy composer script

echo "Running theme build script for $themename"
./scripts/ci/theme_build.sh $themename

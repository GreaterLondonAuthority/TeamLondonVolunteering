#!/bin/bash
# Script from CTi Bamboo - via user1
# Modified by user2 & user2
# 7th October 2015

# Updated: 10/04/2017 by user2 - Adapted runGrunt from npm build
# Updated: 25/01/2017 by user2 - Set WORKSAPCE to current dir

if [ -z "${WORKSPACE}" ]; then
    echo "WORKSPACE is unset or set to the empty string"
    echo "adding $(pwd) to WORKSPACE"
    WORKSPACE=$(pwd)
fi
echo "WORKSPACE is set to $WORKSPACE"

## loop through each $theme passed as an argument
for THEME in "$@"
do
  THEME_DIR=$WORKSPACE/web/themes/custom/$THEME/
  echo Theme Directory $THEME_DIR
  cd $THEME_DIR
  pwd

  # Clear up temporary directories
  echo Clearing WORKSPACE and THEME directories
  rm -rf $THEME_DIR/dist
  rm -rf $THEME_DIR/node_modules

  # Build the theme
  echo Building Theme
  echo Installing Node Modules
  npm install
  echo Compiling assets
  npm run build

  # Clear up temporary directories
  echo Clearing WORKSPACE and THEME directories
  rm -rf $THEME_DIR/node_modules

done

exit

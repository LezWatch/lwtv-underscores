#!/usr/bin/env sh
# Composer script: Copy Bootstrap and Popper assets to theme directories.
# Replaces slowprog/composer-copy-file (unmaintained since 2020).
# Run via: composer post-install-cmd / post-update-cmd

set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# Bootstrap CSS
if [ -d "$ROOT/vendor/twbs/bootstrap/dist/css" ]; then
	if [ -d "$ROOT/inc/bootstrap" ]; then
		mkdir -p "$ROOT/inc/bootstrap"

		if [ $? -ne 0 ]; then
			echo 'Error: Failed to create inc/bootstrap directory.'
			exit 1
		fi
	fi

	cp -r "$ROOT/vendor/twbs/bootstrap/dist/css" "$ROOT/inc/bootstrap/"

	if [ $? -ne 0 ]; then
		echo 'Error copying Bootstrap CSS.'
		exit 1
	fi

	echo 'Bootstrap CSS copied to theme directory'
fi

# Bootstrap JS
if [ -d "$ROOT/vendor/twbs/bootstrap/dist/js" ]; then
	if [ -d "$ROOT/inc/bootstrap" ]; then
		mkdir -p "$ROOT/inc/bootstrap"

		if [ $? -ne 0 ]; then
			echo 'Error: Failed to create inc/bootstrap directory.'
			exit 1
		fi
	fi

	cp -r "$ROOT/vendor/twbs/bootstrap/dist/js" "$ROOT/inc/bootstrap/"

	if [ $? -ne 0 ]; then
		echo 'Error copying Bootstrap JS.'
		exit 1
	fi

	echo 'Bootstrap JS copied to theme directory.'
fi

# Popper
if [ -f "$ROOT/node_modules/@popperjs/core/dist/umd/popper.min.js" ]; then
	if [ -d "$ROOT/inc/js" ]; then
		mkdir -p "$ROOT/inc/js"

		if [ $? -ne 0 ]; then
			echo 'Error: Failed to create inc/js directory.'
			exit 1
		fi
	fi

	cp "$ROOT/node_modules/@popperjs/core/dist/umd/popper.min.js" "$ROOT/inc/js/"

	if [ $? -ne 0 ]; then
		echo 'Error copying Popper.min.js.'
		exit 1
	fi

	cp "$ROOT/node_modules/@popperjs/core/dist/umd/popper.min.js.map" "$ROOT/inc/js/"

	if [ $? -ne 0 ]; then
		echo 'Error copying Popper.min.js.map.'
		exit 1
	fi

	echo 'Popper.min.js and Popper.min.js.map copied to theme directory.'
fi

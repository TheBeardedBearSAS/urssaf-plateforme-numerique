#!/bin/sh
# This file is part of thebeardedbear/urssaf-plateforme-numerique.
#
# Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
#
# SPDX-License-Identifier: MIT

set -e

# Xdebug is only loaded when XDEBUG_MODE is set and not "off".
# In that case pcov is disabled (the two are mutually exclusive for coverage).
if [ -n "${XDEBUG_MODE:-}" ] && [ "${XDEBUG_MODE}" != "off" ]; then
    export PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:/usr/local/etc/php/xdebug.d"
fi

exec "$@"

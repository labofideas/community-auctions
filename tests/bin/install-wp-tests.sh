#!/usr/bin/env bash
set -e

DB_NAME=${1-wordpress_test}
DB_USER=${2-root}
DB_PASS=${3-root}
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress}

download() {
  if command -v curl >/dev/null 2>&1; then
    curl -s "$1" -o "$2"
  elif command -v wget >/dev/null 2>&1; then
    wget -q -O "$2" "$1"
  else
    echo "Neither curl nor wget found." >&2
    exit 1
  fi
}

svn_export() {
  if ! command -v svn >/dev/null 2>&1; then
    echo "svn is required to download the WordPress test suite." >&2
    exit 1
  fi
  svn export -q "$1" "$2"
}

set_up_wp_core() {
  if [ ! -d "$WP_CORE_DIR" ]; then
    mkdir -p "$WP_CORE_DIR"
    if [ "$WP_VERSION" = "latest" ]; then
      download https://wordpress.org/latest.tar.gz /tmp/wordpress.tar.gz
    else
      download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" /tmp/wordpress.tar.gz
    fi
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
  fi
}

set_up_test_lib() {
  if [ ! -d "$WP_TESTS_DIR" ]; then
    mkdir -p "$WP_TESTS_DIR"
    svn_export https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/ "$WP_TESTS_DIR/includes"
    svn_export https://develop.svn.wordpress.org/trunk/tests/phpunit/data/ "$WP_TESTS_DIR/data"
  fi

  if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
    download https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i.bak "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i.bak "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i.bak "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i.bak "s|localhost|$DB_HOST|" "$WP_TESTS_DIR/wp-tests-config.php"
  fi
}

create_db() {
  if command -v mysql >/dev/null 2>&1; then
    mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" >/dev/null 2>&1 || true
  fi
}

set_up_wp_core
set_up_test_lib
create_db

echo "WordPress test suite installed."

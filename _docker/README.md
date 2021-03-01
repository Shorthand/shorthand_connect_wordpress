## Wordpress docker setup

Start environment (from root directory).

```
docker-compose --file _docker/docker-compose.yml up --detach --build
```

Website is available at [0.0.0.0:8000](http://0.0.0.0:8000) and you should be prompted to complete the install - if not, see below.

---

### If the Wordpress Installation comes pre-configured:

Install WP CLI to easily change admin password (we'll make it `W0rdpress`)

```
docker-compose --file _docker/docker-compose.yml exec wordpress bash -c "curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp && wp user update 1 --user_pass=W0rdpress --allow-root"
```

You can ignore any `sendmail` errors.

---

### To stop and remove containers run

```
docker-compose --file _docker/docker-compose.yml stop
docker-compose --file _docker/docker-compose.yml rm -f
```

### To clear persisant data:

To see if `shorthand_connect_wordpress_db_data` exists.

```
docker volume ls
```

If it does, remove it.

```
docker volume rm shorthand_connect_wordpress_db_data
```

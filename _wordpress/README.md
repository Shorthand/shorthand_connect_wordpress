## Wordpress docker setup

Start environment (from root directory).

```
docker-compose --file _wordpress/docker-compose.yml up --detach --build
```

Website is available at [0.0.0.0:8000](http://0.0.0.0:8000) and you should be prompted to complete the install.

---

### Install Wordpress CLI (For Plugin Activation and Admin commands):

Curl and Install WP CLI

```
docker-compose --file _wordpress/docker-compose.yml exec wordpress bash -c "curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp"
```

Activate Shorthand Plugin

```
docker-compose --file _wordpress/docker-compose.yml exec wordpress bash -c "wp plugin activate shorthand_wordpress_connect --allow-root"
```

Change admin password (replace `W0rdpress` with whatever you want)

```
docker-compose --file _wordpress/docker-compose.yml exec wordpress bash -c "wp user update 1 --user_pass=W0rdpress --allow-root"
```

You can ignore any `sendmail` errors.

---

### To stop and remove containers run

```
docker-compose --file _wordpress/docker-compose.yml stop
docker-compose --file _wordpress/docker-compose.yml rm -f
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

### Local Docker Dev Networking

#create bridge network
docker network create WPDevNetwork

#connect dylan_dev
docker network connect WPDevNetwork dev_dylan

#connect web (drupal)
docker network connect WPDevNetwork web (or whatever the name of the WP container is)

#inspect network to see ip address of dylan_dev (in the below command we've assumed 172.18.0.2)
docker network inspect WPDevNetwork

#set api.dylan.local in /etc/hosts of drupal container (change 172.18.0.2 if the above ip address shows differently)
docker-compose --file \_wordpress/docker-compose.yml exec web bash -c "echo '172.18.0.2 app.dylan.local' >> /etc/hosts"

#Ensure the correct api url is being used for local dev - in ShorthandApiv2.php:
$serverv2URL = 'app.dylan.local/api';

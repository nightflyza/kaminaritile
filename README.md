# KaminariTile
Easy to setup OpenStreetMap tile caching server

[OpenStreetMap`s Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)

# System requirements

  * Apache 1.3/2.2/2.4 web server
  * PHP 5.3-8.4 + CURL extension

# FreeBSD quick setup

```
# cd /usr/local/www/apache24/data/
# fetch https://github.com/nightflyza/kaminaritile/archive/refs/heads/main.zip
# unzip main.zip
# mv kaminaritile-main kaminaritile
# rm -fr main.zip
# chmod -R 777 kaminaritile/cache
```


# Linux quick setup

```
# cd /var/www/html/
# wget https://github.com/nightflyza/kaminaritile/archive/refs/heads/main.zip
# unzip main.zip
# mv kaminaritile-main kaminaritile
# rm -fr main.zip
# chmod -R 777 kaminaritile/cache
```

# Usage examples

[Ubilling](https://wiki.ubilling.net.ua/doku.php?id=switchmap) just put following option into config/ymaps.ini config file:

```
LEAFLET_TILE_LAYER="https://yourtileserver.ua/kaminaritile/?t={s}_{z}_{x}_{y}"
```

<img width="1548" height="779" alt="kaminaritile" src="https://github.com/user-attachments/assets/7a8005ea-3007-4b46-8ebb-503db14fc259" />


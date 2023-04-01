# KaminariTile
Easy to setup OpenStreetMap tile caching server

[OpenStreetMap`s Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)

# System requirements

  * Apache 1.3/2.2/2.4 web server
  * PHP 5.3-7.4-8.2 + CURL extension

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

![kaminaritile](https://user-images.githubusercontent.com/1496954/229291357-63c7deb3-5221-4e48-b478-2e9c45a3d591.png)


# KaminariTile
Easy to setup OpenStreetMap tile caching server

# System requirements

  * Apache 1.3/2.2/2.4 web server
  * PHP 5.3-7.4-8.2 + CURL extension

# FreeBSD quick setup on 

```
# cd /usr/local/www/apache24/data/
# fetch https://github.com/nightflyza/kaminaritile/archive/refs/heads/main.zip
# unzip main.zip
# mv kaminaritile-main kaminaritile
# rm -fr main.zip
# chmod -R 777 kaminaritile/cache
```


# Linux quick setup on 

```
# cd /var/www/html/
# wget https://github.com/nightflyza/kaminaritile/archive/refs/heads/main.zip
# unzip main.zip
# mv kaminaritile-main kaminaritile
# rm -fr main.zip
# chmod -R 777 kaminaritile/cache
```

# Usage examples
  * Ubilling maps: LEAFLET_TILE_LAYER="https://yourtileserver.ua/kaminaritile/?t={s}_{z}_{x}_{y}"


[OpenStreetMap`s Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)
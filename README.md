# 馃敆 url-destroyer

鍩轰簬 PHP + SQLite 鐨?*涓€娆℃€ч摼鎺ョ鐞嗙郴缁?*銆傚彲瑙嗗寲琛ㄥ崟鏋勫缓銆佸畾鏃堕攢姣併€佽闂拷韪€丆SV 瀵煎嚭锛孌ocker 涓€閿儴缃层€?
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-8.2-777bb4.svg)](https://php.net)
[![Docker](https://img.shields.io/badge/docker-ready-2496ed.svg)](https://docker.com)

## 鉁?鍔熻兘

### 閾炬帴绠＄悊
| 鍔熻兘 | 璇存槑 |
|---|---|
| 鈴?瀹氭椂閿€姣?| 棣栨鎵撳紑鍚?N 鍒嗛挓鑷姩澶辨晥锛堥粯璁?10 鍒嗛挓锛屽彲閰嶏級 |
| 馃晲 鑷姩杩囨湡 | 鍒涘缓鍚?N 灏忔椂鏈璁块棶鑷姩澶辨晥锛堥粯璁?24 灏忔椂锛?|
| 馃敘 鎵归噺鐢熸垚 | 涓€娆″垱寤?1~500 涓嫭绔嬮摼鎺?|
| 馃攧 閲嶆柊鎵撳紑 | 宸茶繃鏈熼摼鎺ヤ竴閿仮澶嶏紙瓒呯粷瀵硅繃鏈熸椂闂村垯姘镐箙澶辨晥锛?|
| 鈴?鎵嬪姩杩囨湡 | 闅忔椂灏嗛摼鎺ョ疆涓哄凡杩囨湡 |
| 馃攳 鎼滅储绛涢€?| 鎸夋椿鍔ㄥ悕绉帮紙妯＄硦锛夈€佹棩鏈熻寖鍥淬€佺姸鎬佺瓫閫?|
| 馃摜 CSV 瀵煎嚭 | 鎸夌瓫閫夋潯浠跺鍑哄凡鎻愪氦鐨勮〃鍗曟暟鎹?|

### 琛ㄥ崟鏋勫缓鍣?| 鍔熻兘 | 璇存槑 |
|---|---|
| 馃帹 鍙鍖栫紪杈?| 鎷栨嫿寮忔坊鍔?鍒犻櫎瀛楁锛屽彸渚у疄鏃堕瑙?|
| 馃摑 瀛楁绫诲瀷 | 鏂囨湰銆侀偖绠便€佺數璇濄€佹暟瀛椼€佹棩鏈熴€佷笅鎷夋銆佸琛屾枃鏈?|
| 鈿欙笍 瀛楁灞炴€?| 鏍囩銆佸繀濉€佸崰浣嶆枃瀛椼€侀粯璁ゅ€笺€佷笅鎷夐€夐」 |
| 馃搵 閰嶇疆澶嶇敤 | 浠庡凡鏈夐摼鎺ヤ竴閿鍒惰〃鍗曡璁″埌鏂伴摼鎺?|
| 馃搫 楂樼骇妯″紡 | 鍒囨崲鍒?PHP 浠ｇ爜妯″紡锛屾敮鎸佸鏉傞€昏緫 |

### 绠＄悊鍚庡彴
| 鍔熻兘 | 璇存槑 |
|---|---|
| 馃搳 浠〃鐩?| 閾炬帴/璁块棶缁熻姒傝 |
| 馃搵 閾炬帴鍒楄〃 | 鐘舵€佺瓫閫夈€佹悳绱€佺紪杈戙€佸垹闄ゃ€佷竴閿鍒惰闂摼鎺?|
| 馃搱 璁块棶璇︽儏 | 姣忔璁块棶鐨?IP銆乁A銆丷eferer銆佹彁浜ゆ暟鎹紝琛ㄥ崟棰勮 |
| 鈿欙笍 绯荤粺璁剧疆 | 榛樿瓒呮椂閰嶇疆銆佸湪绾夸慨鏀瑰瘑鐮侊紙瀹炴椂鐢熸晥锛?|
| 馃摫 鍝嶅簲寮?| 妗岄潰绔晶杈规爮鍙姌鍙狅紝绉诲姩绔嚜鍔ㄩ€傞厤 |
| 馃幁 鑷畾涔夎矾寰?| 淇敼鍚庡彴鍏ュ彛 URL 闃叉壂鎻?|

## 馃殌 蹇€熷紑濮?
### 1. 鍏嬮殕

```bash
git clone https://github.com/Mitchll1214/url-destroyer.git
cd url-destroyer
```

### 2. 淇敼鍒濆瀵嗙爜

缂栬緫 `www/config.php`锛?
```php
define('ADMIN_PASSWORD', '浣犵殑寮哄瘑鐮?);
```

> 閮ㄧ讲鍚庝篃鍙湪鍚庡彴銆岃缃€嶉〉闈㈠湪绾夸慨鏀癸紝鏃犻渶閲嶅惎锛岄粯璁ゅ瘑鐮乤dmin123銆?
### 3. 鍚姩

```bash
docker-compose up -d --build
```

### 4. 璁块棶

```
绠＄悊鍚庡彴: http://localhost:8087/admin/
```

## 馃搧 椤圭洰缁撴瀯

```
url-destroyer/
鈹溾攢鈹€ Dockerfile                  # PHP 8.2 + Apache + SQLite锛堣吘璁簯 apt 婧愶級
鈹溾攢鈹€ docker-compose.yml          # 绔彛 8087锛屾暟鎹寔涔呭寲
鈹溾攢鈹€ docker-entrypoint.sh        # 瀹瑰櫒鍚姩鏉冮檺淇
鈹溾攢鈹€ data/                       # SQLite 鏁版嵁搴擄紙鎸傝浇鍗凤級
鈹溾攢鈹€ templates/
鈹?  鈹斺攢鈹€ default_form.php        # 榛樿琛ㄥ崟妯℃澘锛堝鐢級
鈹斺攢鈹€ www/
    鈹溾攢鈹€ .htaccess               # URL 閲嶅啓
    鈹溾攢鈹€ config.php              # 瀵嗙爜銆佹椂鍖恒€丄DMIN_PATH銆丅ASE_URL
    鈹溾攢鈹€ db.php                  # SQLite 鍒濆鍖?+ PDO
    鈹溾攢鈹€ index.php               # 鈫?閲嶅畾鍚戝埌鍚庡彴
    鈹溾攢鈹€ access.php              # 馃攽 鍏紑璁块棶鍏ュ彛锛堟牳蹇冨紩鎿庯級
    鈹溾攢鈹€ assets/style.css        # 鍝嶅簲寮忔牱寮?    鈹斺攢鈹€ admin/
        鈹溾攢鈹€ _lib.php            # 鐧诲綍璁よ瘉 + 甯冨眬 + 渚ц竟鏍忔姌鍙?        鈹溾攢鈹€ index.php           # 浠〃鐩?        鈹溾攢鈹€ create.php          # 鍙鍖栬〃鍗曟瀯寤哄櫒 + 閾炬帴鐢熸垚
        鈹溾攢鈹€ links.php           # 閾炬帴鍒楄〃锛堟悳绱?绛涢€?缂栬緫/鍒犻櫎锛?        鈹溾攢鈹€ stats.php           # 璁块棶璇︽儏 + 琛ㄥ崟棰勮
        鈹溾攢鈹€ settings.php        # 瓒呮椂榛樿鍊?+ 鍦ㄧ嚎鏀瑰瘑
        鈹斺攢鈹€ export.php          # CSV 鏁版嵁瀵煎嚭
```

## 馃洜 鎶€鏈爤

| 灞?| 鎶€鏈?|
|---|---|
| 璇█ | PHP 8.2 |
| Web 鏈嶅姟鍣?| Apache 2.4 + mod_rewrite |
| 鏁版嵁搴?| SQLite 3 (WAL 妯″紡) |
| 瀹瑰櫒 | Docker + docker-compose |
| 鍓嶇 | 鍘熺敓 HTML/CSS/JS锛堥浂渚濊禆锛?|
| 鏃跺尯 | Asia/Shanghai锛堝寳浜椂闂达級 |

## 馃搵 浣跨敤娴佺▼

### 鍒涘缓閾炬帴

1. 鐧诲綍鍚庡彴 鈫?**鍒涘缓閾炬帴**
2. 濉啓娲诲姩鍚嶇О銆佹暟閲忋€佽繃鏈熺瓥鐣?3. 鍦ㄥ彲瑙嗗寲鏋勫缓鍣ㄨ璁¤〃鍗曪細
   - 鏍囬銆佸壇鏍囬銆佹彁浜ゆ寜閽枃瀛?   - 娣诲姞瀛楁銆侀€夋嫨绫诲瀷銆佽缃爣绛惧拰榛樿鍊?   - 鍙充晶瀹炴椂棰勮
4. 鐐瑰嚮 **鐢熸垚閾炬帴** 鈫?澶嶅埗 URL 鍒嗗彂缁欑敤鎴?
### 閾炬帴鐢熷懡鍛ㄦ湡

```
鍒涘缓 (active) 鈫?鐢ㄦ埛鎵撳紑 (opened) 鈫?鎻愪氦琛ㄥ崟 鈫?瓒呮椂 (expired)
                                          鈫?                                    绠＄悊鍛樺彲閲嶆柊鎵撳紑
                                          鈫?                              缁濆杩囨湡鍚庢案涔呭け鏁?```

### 鏁版嵁瀵煎嚭

閾炬帴鍒楄〃椤电瓫閫夋潯浠跺悗锛岀偣鍑?**馃摜 瀵煎嚭CSV**锛屼笅杞藉寘鍚墍鏈夎〃鍗曟彁浜ゆ暟鎹殑 CSV 鏂囦欢锛圲TF-8 BOM锛孍xcel 鐩存帴鎵撳紑锛夈€?
## 鈿欙笍 閰嶇疆

### 鑷畾涔夊悗鍙拌矾寰?
`www/config.php`锛?
```php
define('ADMIN_PATH', 'my-secret-panel');
```

`www/.htaccess` 娣诲姞锛?
```apache
RewriteRule ^my-secret-panel/(.*)$ admin/$1 [L,QSA]
```

### 榛樿瓒呮椂

鍚庡彴 鈫?璁剧疆 鈫?淇敼榛樿鍊硷紙姣忎釜閾炬帴鍒涘缓鏃跺彲鍗曠嫭瑕嗙洊锛夈€?
### 鍙嶅悜浠ｇ悊 / 鑷畾涔夊煙鍚?
`www/config.php`锛?
```php
define('BASE_URL', 'https://your-domain.com');
```

### 淇敼绔彛

`docker-compose.yml`锛?
```yaml
ports:
  - "浣犵殑绔彛:80"
```

## 馃寪 鍥藉唴閮ㄧ讲

宸查€傞厤鑵捐浜戠綉缁滅幆澧冿紙apt 婧?`mirrors.cloud.tencent.com`锛夛細

```bash
# Docker 闀滃儚鍔犻€?sudo tee /etc/docker/daemon.json <<'EOF'
{ "registry-mirrors": ["https://mirror.ccs.tencentyun.com"] }
EOF
sudo systemctl daemon-reload && sudo systemctl restart docker

docker-compose build --no-cache && docker-compose up -d
```

## 馃搳 鏁版嵁搴?
### links

| 瀛楁 | 绫诲瀷 | 璇存槑 |
|---|---|---|
| id | INTEGER | 涓婚敭 |
| token | TEXT | 32 浣?hex 鍞竴鏍囪瘑 |
| campaign_name | TEXT | 娲诲姩鍚嶇О |
| target_content | TEXT | 琛ㄥ崟 JSON 鎴栭潤鎬?HTML 浠ｇ爜 |
| access_timeout | INTEGER | 棣栨璁块棶鍚庤秴鏃讹紙绉掞級 |
| absolute_expiry_hours | INTEGER | 鍒涘缓鍚庣粷瀵硅繃鏈燂紙灏忔椂锛?|
| max_accesses | INTEGER | 鏈€澶ц闂鏁?|
| access_count | INTEGER | 宸茶闂鏁?|
| status | TEXT | active / opened / expired |
| created_at | TEXT | 鍒涘缓鏃堕棿 |
| first_accessed_at | TEXT | 棣栨璁块棶鏃堕棿 |
| expires_at | TEXT | 杩囨湡鏃堕棿 |

### access_logs

| 瀛楁 | 绫诲瀷 | 璇存槑 |
|---|---|---|
| id | INTEGER | 涓婚敭 |
| link_id | INTEGER | 澶栭敭 鈫?links.id |
| ip | TEXT | 璁块棶鑰?IP |
| user_agent | TEXT | 娴忚鍣?UA |
| referer | TEXT | 鏉ユ簮椤甸潰 |
| form_data | TEXT | 鎻愪氦鐨勮〃鍗曟暟鎹紙JSON锛?|
| accessed_at | TEXT | 璁块棶鏃堕棿 |

## 馃搫 License

MIT 漏 [Mitchll1214](https://github.com/Mitchll1214)


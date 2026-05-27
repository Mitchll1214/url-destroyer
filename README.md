# 🔗 动态网址销毁系统 (Link Destroyer)

一个基于 PHP + SQLite 的**一次性链接管理系统**，支持可视化表单构建、定时销毁、访问追踪，Docker 一键部署。

## ✨ 核心功能

| 功能 | 说明 |
|---|---|
| ⏱ **定时销毁** | 链接打开后 N 分钟自动失效（默认 10 分钟，可后台配置） |
| 🕐 **自动过期** | 创建后超过 N 小时未打开则自动失效（默认 24 小时） |
| 🎨 **可视化表单构建器** | 拖拽式添加字段，支持文本/邮箱/电话/数字/日期/下拉框/多行文本 |
| 📊 **访问追踪** | 每次访问记录 IP、UA、Referer、提交的表单数据 |
| 🔢 **批量生成** | 一次创建 1~500 个独立链接 |
| 👁 **管理后台** | 仪表盘统计、链接列表、访问详情、数据查看 |
| 🐳 **Docker 部署** | 一条命令启动，SQLite 零配置 |
| 📱 **响应式** | 管理后台和表单页面均适配移动端 |

## 🚀 快速开始

### 1. 克隆项目

```bash
git clone https://github.com/Mitchll1214/link-destroyer.git
cd link-destroyer
```

### 2. 修改管理员密码

```bash
# 编辑 www/config.php，修改 ADMIN_PASSWORD
sed -i "s/admin123/你的强密码/" www/config.php
```

### 3. 启动

```bash
docker-compose up -d --build
```

### 4. 访问

```
管理后台: http://localhost:8087/admin/
默认密码: 你在第2步设置的密码
```

## 📁 项目结构

```
├── Dockerfile                  # PHP 8.2 + Apache + SQLite
├── docker-compose.yml          # 端口 8087，数据持久化
├── docker-entrypoint.sh        # 容器启动权限修复
├── data/                       # SQLite 数据库文件（挂载卷）
├── templates/
│   └── default_form.php        # 默认表单模板（备用）
└── www/
    ├── .htaccess               # URL 重写
    ├── config.php              # 全局配置（密码、时区、路径）
    ├── db.php                  # 数据库初始化 + PDO 连接
    ├── index.php               # 根路径重定向
    ├── access.php              # 🔑 公开访问入口（核心引擎）
    ├── assets/
    │   └── style.css           # 管理后台样式
    └── admin/
        ├── _lib.php            # 登录认证 + 布局
        ├── index.php           # 仪表盘
        ├── create.php          # 创建链接 + 表单构建器
        ├── links.php           # 链接列表管理
        ├── stats.php           # 单链接访问详情
        └── settings.php        # 全局超时设置
```

## 🛠 技术栈

| 层 | 技术 |
|---|---|
| 语言 | PHP 8.2 |
| Web 服务器 | Apache 2.4 + mod_rewrite |
| 数据库 | SQLite 3 (WAL 模式) |
| 容器化 | Docker + docker-compose |
| 前端 | 原生 HTML/CSS/JavaScript（零依赖） |

## 📋 使用流程

### 管理员操作

1. 登录后台 → 点击「创建链接」
2. 填写活动名称，设置链接数量和过期策略
3. 在**可视化构建器**中编辑表单：
   - 设置表单标题、副标题
   - 添加 / 删除字段
   - 设置字段类型、标签、是否必填、默认值
   - 右侧实时预览效果
4. 点击「生成链接」→ 复制生成的 URL 分发给用户

### 用户访问

1. 用户打开链接 → 看到你设计的表单页面
2. 填写并提交 → 数据自动记录到后台
3. 链接在首次打开后开始计时，超时后自动失效

## ⚙️ 配置说明

### 自定义后台路径

编辑 `www/config.php`：

```php
define('ADMIN_PATH', 'my-secret-panel');  // 替换默认的 admin
```

然后在 `www/.htaccess` 添加一条 rewrite：

```apache
RewriteRule ^my-secret-panel/(.*)$ admin/$1 [L,QSA]
```

### 修改默认超时

后台 → 设置 → 修改「首次访问后超时」和「未打开自动过期」。所有新建链接将使用此默认值（每个链接可单独覆盖）。

### 手动指定域名

如果反向代理未正确传递 Host 头，在 `www/config.php` 手动设置：

```php
define('BASE_URL', 'https://your-domain.com');
```

### 修改端口

编辑 `docker-compose.yml`：

```yaml
ports:
  - "你的端口:80"
```

## 🌐 国内服务器部署

项目已适配国内网络环境：

```bash
# 腾讯云 Docker 镜像加速
sudo tee /etc/docker/daemon.json <<'EOF'
{ "registry-mirrors": ["https://mirror.ccs.tencentyun.com"] }
EOF
sudo systemctl daemon-reload && sudo systemctl restart docker

# Dockerfile 内 apt 源已替换为腾讯云镜像，直接构建即可
docker-compose build --no-cache && docker-compose up -d
```

## 📊 数据库结构

### links 表

| 字段 | 类型 | 说明 |
|---|---|---|
| id | INTEGER | 主键 |
| token | TEXT | 唯一访问标识（32位 hex） |
| campaign_name | TEXT | 活动名称 |
| target_content | TEXT | 目标页面内容（JSON 或 PHP） |
| access_timeout | INTEGER | 首次访问后超时秒数 |
| absolute_expiry_hours | INTEGER | 未打开自动过期小时数 |
| max_accesses | INTEGER | 最大访问次数 |
| access_count | INTEGER | 已访问次数 |
| status | TEXT | active / opened / expired |
| created_at | TEXT | 创建时间（北京时间） |
| first_accessed_at | TEXT | 首次访问时间 |
| expires_at | TEXT | 过期时间 |

### access_logs 表

| 字段 | 类型 | 说明 |
|---|---|---|
| id | INTEGER | 主键 |
| link_id | INTEGER | 外键 → links.id |
| ip | TEXT | 访问者 IP |
| user_agent | TEXT | 浏览器 UA |
| referer | TEXT | 来源页面 |
| form_data | TEXT | 提交的表单数据（JSON） |
| accessed_at | TEXT | 访问时间（北京时间） |

## 📄 License

MIT

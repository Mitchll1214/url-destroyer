# 🔗 url-destroyer

基于 PHP + SQLite 的**一次性链接管理系统**。可视化表单构建、定时销毁、访问追踪、CSV 导出，Docker 一键部署。

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-8.2-777bb4.svg)](https://php.net)
[![Docker](https://img.shields.io/badge/docker-ready-2496ed.svg)](https://docker.com)

## ✨ 功能

### 链接管理
| 功能 | 说明 |
|---|---|
| ⏱ 定时销毁 | 首次打开后 N 分钟自动失效（默认 10 分钟，可配） |
| 🕐 自动过期 | 创建后 N 小时未被访问自动失效（默认 24 小时） |
| 🔢 批量生成 | 一次创建 1~500 个独立链接 |
| 🔄 重新打开 | 已过期链接一键恢复（超绝对过期时间则永久失效） |
| ⏹ 手动过期 | 随时将链接置为已过期 |
| 🔍 搜索筛选 | 按活动名称（模糊）、日期范围、状态筛选 |
| 📥 CSV 导出 | 按筛选条件导出已提交的表单数据 |

### 表单构建器
| 功能 | 说明 |
|---|---|
| 🎨 可视化编辑 | 拖拽式添加/删除字段，右侧实时预览 |
| 📝 字段类型 | 文本、邮箱、电话、数字、日期、下拉框、多行文本 |
| ⚙️ 字段属性 | 标签、必填、占位文字、默认值、下拉选项 |
| 📋 配置复用 | 从已有链接一键复制表单设计到新链接 |
| 📄 高级模式 | 切换到自定义 HTML 模式（PHP 代码不会执行，已做安全过滤） |

### 管理后台
| 功能 | 说明 |
|---|---|
| 📊 仪表盘 | 链接/访问统计概览 |
| 📋 链接列表 | 状态筛选、搜索、编辑、删除、一键复制访问链接 |
| 📈 访问详情 | 每次访问的 IP、UA、Referer、提交数据，表单预览 |
| ⚙️ 系统设置 | 默认超时配置、在线修改密码（实时生效） |
| 🔒 安全加固 | 登录速率限制（5 次/10 分钟锁定 60 秒）、PHP 标签过滤 |
| 📱 响应式 | 桌面端侧边栏可折叠，移动端自动适配 |
| 🎭 自定义路径 | 修改后台入口 URL 防扫描 |

## 🚀 快速开始

### 1. 克隆

```bash
git clone https://github.com/Mitchll1214/url-destroyer.git
cd url-destroyer
```

### 2. 修改初始密码

编辑 `www/config.php`：

```php
define('ADMIN_PASSWORD', '你的强密码');
```

> 部署后也可在后台「设置」页面在线修改，无需重启，默认密码 admin123。

### 3. 启动

```bash
docker-compose up -d --build
```

### 4. 访问

```
管理后台: http://localhost:8087/admin/
```

## 📁 项目结构

```
url-destroyer/
├── Dockerfile                  # PHP 8.2 + Apache + SQLite（腾讯云 apt 源）
├── docker-compose.yml          # 端口 8087，数据持久化
├── docker-entrypoint.sh        # 容器启动权限修复
├── data/                       # SQLite 数据库（挂载卷）
├── templates/
│   └── default_form.php        # 默认表单模板（备用）
└── www/
    ├── .htaccess               # URL 重写
    ├── config.php              # 密码、时区、ADMIN_PATH、BASE_URL
    ├── db.php                  # SQLite 初始化 + PDO
    ├── index.php               # → 重定向到后台
    ├── access.php              # 🔑 公开访问入口（核心引擎）
    ├── assets/style.css        # 响应式样式
    └── admin/
        ├── _lib.php            # 登录认证 + 布局 + 速率限制
        ├── index.php           # 仪表盘
        ├── create.php          # 可视化表单构建器 + 链接生成
        ├── links.php           # 链接列表（搜索/筛选/编辑/删除）
        ├── stats.php           # 访问详情 + 表单预览
        ├── settings.php        # 超时默认值 + 在线改密
        └── export.php          # CSV 数据导出
```

## 🛠 技术栈

| 层 | 技术 |
|---|---|
| 语言 | PHP 8.2 |
| Web 服务器 | Apache 2.4 + mod_rewrite |
| 数据库 | SQLite 3 (WAL 模式) |
| 容器 | Docker + docker-compose |
| 前端 | 原生 HTML/CSS/JS（零依赖） |
| 时区 | Asia/Shanghai（北京时间） |

## 📋 使用流程

### 创建链接

1. 登录后台 → **创建链接**
2. 填写活动名称、数量、过期策略
3. 在可视化构建器设计表单：
   - 标题、副标题、提交按钮文字
   - 添加字段、选择类型、设置标签和默认值
   - 右侧实时预览
4. 点击 **生成链接** → 复制 URL 分发给用户

### 链接生命周期

```
创建 (active) → 用户打开 (opened) → 提交表单 → 超时 (expired)
                                          ↓
                                    管理员可重新打开
                                          ↓
                              绝对过期后永久失效
```

### 数据导出

链接列表页筛选条件后，点击 **📥 导出CSV**，下载包含所有表单提交数据的 CSV 文件（UTF-8 BOM，Excel 直接打开）。

## ⚙️ 配置

### 自定义后台路径

`www/config.php`：

```php
define('ADMIN_PATH', 'my-secret-panel');
```

`www/.htaccess` 添加：

```apache
RewriteRule ^my-secret-panel/(.*)$ admin/$1 [L,QSA]
```

### 默认超时

后台 → 设置 → 修改默认值（每个链接创建时可单独覆盖）。

### 反向代理 / 自定义域名

`www/config.php`：

```php
define('BASE_URL', 'https://your-domain.com');
```

### 修改端口

`docker-compose.yml`：

```yaml
ports:
  - "你的端口:80"
```

## 🌐 国内部署

已适配腾讯云网络环境（apt 源 `mirrors.cloud.tencent.com`）：

```bash
# Docker 镜像加速
sudo tee /etc/docker/daemon.json <<'EOF'
{ "registry-mirrors": ["https://mirror.ccs.tencentyun.com"] }
EOF
sudo systemctl daemon-reload && sudo systemctl restart docker

docker-compose build --no-cache && docker-compose up -d
```

## 📊 数据库

### links

| 字段 | 类型 | 说明 |
|---|---|---|
| id | INTEGER | 主键 |
| token | TEXT | 32 位 hex 唯一标识 |
| campaign_name | TEXT | 活动名称 |
| target_content | TEXT | 表单 JSON 或静态 HTML 代码 |
| access_timeout | INTEGER | 首次访问后超时（秒） |
| absolute_expiry_hours | INTEGER | 创建后绝对过期（小时） |
| max_accesses | INTEGER | 最大访问次数 |
| access_count | INTEGER | 已访问次数 |
| status | TEXT | active / opened / expired |
| created_at | TEXT | 创建时间 |
| first_accessed_at | TEXT | 首次访问时间 |
| expires_at | TEXT | 过期时间 |

### access_logs

| 字段 | 类型 | 说明 |
|---|---|---|
| id | INTEGER | 主键 |
| link_id | INTEGER | 外键 → links.id |
| ip | TEXT | 访问者 IP |
| user_agent | TEXT | 浏览器 UA |
| referer | TEXT | 来源页面 |
| form_data | TEXT | 提交的表单数据（JSON） |
| accessed_at | TEXT | 访问时间 |

### login_attempts

| 字段 | 类型 | 说明 |
|---|---|---|
| id | INTEGER | 主键 |
| ip | TEXT | 尝试登录的 IP |
| attempted_at | TEXT | 尝试时间 |

## 📄 License

MIT © [Mitchll1214](https://github.com/Mitchll1214)

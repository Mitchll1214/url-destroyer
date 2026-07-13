# 🔗 url-destroyer

基于 PHP + SQLite / MySQL 的**一次性链接管理系统**。可视化表单构建、草稿自动保存、定时销毁、访问追踪、CSV 导出，Docker 一键部署。

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-8.2-777bb4.svg)](https://php.net)
[![Docker](https://img.shields.io/badge/docker-ready-2496ed.svg)](https://docker.com)
[![Image](https://img.shields.io/badge/image-mitchll1214%2Furl--destroyer-2496ed)](https://hub.docker.com/r/mitchll1214/url-destroyer)

## ✨ 功能

### 链接管理
| 功能 | 说明 |
|---|---|
| ⏱ 定时销毁 | 首次打开后 N 小时自动失效（默认 24 小时） |
| 🕐 自动过期 | 创建后 N 小时未访问自动失效（默认 7 天） |
| 🔢 批量生成 | 一次创建 1~500 个独立链接 |
| 👁 访问限制 | 可设最大访问次数，超限自动失效 |
| ✅ 提交即失效 | 可选开关：用户提交表单后立刻过期 |
| 🔄 重新打开 | 已过期链接一键恢复（超绝对过期时间则永久失效） |
| ⏹ 手动过期 | 随时将链接置为已过期 |
| 🔍 搜索筛选 | 按 6 种状态 + 活动名称 + 日期范围筛选 |
| 📥 CSV 导出 | 按筛选条件导出已提交的表单数据（双行表头） |

### 表单构建器
| 功能 | 说明 |
|---|---|
| 🎨 可视化编辑 | 拖拽式添加/删除字段，右侧实时预览 |
| 📝 字段类型 | 文本、邮箱、电话、数字、日期、下拉框、多行文本 |
| ⚙️ 字段属性 | 标签、必填、占位文字、默认值、下拉选项 |
| 💾 草稿自动保存 | 用户输入停止 1.5 秒自动保存到服务端 |
| 📋 断点续填 | 关闭页面后重新打开自动恢复上次填写内容 |
| 🔄 配置复用 | 从已有链接一键复制表单设计 |
| 📄 HTML 模式 | 切换到自定义 HTML（PHP 标签自动过滤防 RCE） |
| 📱 移动适配 | 输入框 16px 防 iOS 缩放，长表单可滚动 |

### 管理后台
| 功能 | 说明 |
|---|---|
| 📊 仪表盘 | 5 项统计卡片 + 最近链接表格（中文状态） |
| 📋 链接列表 | 6 种状态筛选、搜索、编辑、删除、一键复制 |
| 📈 访问详情 | 访问日志、提交数据预览、草稿数据预览 |
| 👁 草稿预览 | 查看用户正在填写中的字段内容 |
| ⚙️ 系统设置 | 默认超时、在线修改密码 |
| 🔒 安全加固 | 登录速率限制（5 次/10 分钟）、CSRF 防护、PHP 标签过滤 |
| 📱 响应式 | 侧边栏折叠 + 图标模式，移动端自适应 |
| 🎭 自定义路径 | 修改后台入口 URL 防扫描 |

## 🚀 快速开始

### 方式一：直接拉取镜像（推荐）

```bash
docker run -d \
  --name url-destroyer \
  -p 8087:80 \
  -v /opt/url-destroyer/data:/var/www/data \
  -e ADMIN_PASSWORD=my-secret-password \
  -e DEFAULT_ACCESS_TIMEOUT=24 \
  -e DEFAULT_ABSOLUTE_EXPIRY_HOURS=168 \
  mitchll1214/url-destroyer:latest
```

> 💡 数据库文件位于宿主机的 `/opt/url-destroyer/data/app.db`（绝对路径，永不丢失）。
> 更新镜像时数据不会受影响：
> ```bash
> docker pull mitchll1214/url-destroyer:latest
> docker rm -f url-destroyer
> # 重新运行上面的 docker run 命令（数据库文件在宿主机上，不会丢失）
> ```

### 方式二：源码构建

```bash
git clone https://github.com/Mitchll1214/url-destroyer.git
cd url-destroyer

# 可选：复制 .env.example 为 .env，自定义数据目录路径
# cp .env.example .env

docker compose up -d --build
```

### 访问

```
管理后台: http://localhost:8087/admin/
默认密码: admin123（请立即修改）
```

## 📁 项目结构

```
url-destroyer/
├── Dockerfile                  # PHP 8.2 + Apache（DaoCloud 镜像）
├── docker-compose.yml          # 端口 8087，data 卷挂载
├── docker-entrypoint.sh        # 容器启动权限修复 + 数据库检测
├── data/                       # SQLite 数据库（挂载卷）
├── .github/workflows/          # CI/CD 自动构建多架构镜像
└── www/
    ├── .htaccess               # URL 重写 + data 目录保护
    ├── config.php              # 全局配置（密码/时区/数据库/URL）
    ├── db.php                  # SQLite / MySQL 双驱动层 + 表前缀
    ├── index.php               # → 重定向到后台
    ├── access.php              # 🔑 公开访问入口（核心引擎）
    ├── assets/style.css        # 响应式样式（CSS 变量主题）
    └── admin/
        ├── _lib.php            # 登录认证 + 布局模板 + 速率限制
        ├── index.php           # 仪表盘
        ├── create.php          # 可视化表单构建器 + 链接生成
        ├── links.php           # 链接列表（6 状态筛选/编辑/删除）
        ├── stats.php           # 访问详情 + 草稿预览 + 表单预览
        ├── settings.php        # 超时默认值 + 在线改密
        └── export.php          # CSV 数据导出（双行表头）
```

## 🛠 技术栈

| 层 | 技术 |
|---|---|
| 语言 | PHP 8.2 |
| Web 服务器 | Apache 2.4 + mod_rewrite |
| 数据库 | SQLite 3 (WAL 模式) / MySQL |
| 容器 | Docker + docker-compose |
| 前端 | 原生 HTML/CSS/JS（零依赖） |
| 时区 | Asia/Shanghai（北京时间） |
| CI/CD | GitHub Actions → DockerHub（amd64 + arm64） |

## 📋 使用流程

### 创建链接

1. 登录后台 → **创建链接**
2. 填写活动名称、数量、过期策略
3. 可选：勾选「提交后立刻失效」
4. 在可视化构建器设计表单：
   - 标题、副标题、提交按钮文字
   - 添加字段、选择类型、设置标签和默认值
   - 右侧实时预览
5. 点击 **生成链接** → 复制 URL 分发给用户

### 链接生命周期

```
创建 (未打开) → 用户打开 (已打开) → 填写中自动保存 (草稿中)
                                            ↓
                                      提交表单 (已提交)
                                       /          \
                              提交即失效 ON    提交即失效 OFF
                                  ↓                ↓
                              已过期           等待超时 → 已过期
                                                   ↓
                                           管理员可重新打开
                                                   ↓
                                         绝对过期后永久失效
```

### 6 种链接状态

| 状态 | 含义 |
|------|------|
| 未打开 | 链接已创建，从未被访问 |
| 已打开 | 已访问但未开始填写 |
| 草稿中 | 正在填写，有自动保存数据 |
| 已提交 | 用户已提交表单，等待超时 |
| 已过期 | 超时 / 访问次数达上限 / 提交即失效 |

### 草稿续填

1. 用户打开链接，开始填写表单
2. 每次输入停止 1.5 秒后，自动保存到服务端
3. 关闭页面，再次打开 → 自动恢复上次填写内容
4. 后台可预览草稿数据

## ⚙️ 环境变量

所有配置均通过环境变量设置，优先级：**后台设置页 > 环境变量 > 默认值**。

### 核心配置

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `ADMIN_PASSWORD` | `admin123` | 管理员初始密码（首次登录后可在后台修改） |
| `DEFAULT_ACCESS_TIMEOUT` | `24` | 首次访问后超时（**小时**） |
| `DEFAULT_ABSOLUTE_EXPIRY_HOURS` | `168` | 创建后未打开自动过期（**小时**） |
| `BASE_URL` | 自动检测 | 站点完整 URL（反向代理/HTTPS 时设置） |
| `ADMIN_PATH` | `admin` | 后台入口路径（修改可防扫描） |

### 数据库配置

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `DB_DRIVER` | `sqlite` | 数据库驱动：`sqlite`（默认）或 `mysql` |
| `DB_TABLE_PREFIX` | `ud_` | 表名前缀（设为空字符串可取消前缀） |

#### SQLite 专用

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `DB_PATH` | `/var/www/data/app.db` | 数据库文件路径 |
| `DATA_DIR` | `./data` | 数据目录（docker-compose 卷挂载变量） |

#### MySQL 专用（设置 `DB_DRIVER=mysql` 后生效）

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `DB_HOST` | `127.0.0.1` | MySQL 服务器地址 |
| `DB_PORT` | `3306` | MySQL 端口 |
| `DB_DATABASE` | `url_destroyer` | 数据库名称 |
| `DB_USERNAME` | `root` | 用户名 |
| `DB_PASSWORD` | （空） | 密码 |
| `DB_CHARSET` | `utf8mb4` | 连接字符集 |

### 部署示例

#### docker run（SQLite）

```bash
docker run -d \
  --name url-destroyer \
  -p 8087:80 \
  -v /opt/url-destroyer/data:/var/www/data \
  -e ADMIN_PASSWORD=my-secret-password \
  -e DEFAULT_ACCESS_TIMEOUT=48 \
  -e DEFAULT_ABSOLUTE_EXPIRY_HOURS=336 \
  -e BASE_URL=https://links.example.com \
  -e ADMIN_PATH=my-secret-panel \
  -e DB_TABLE_PREFIX=ud_ \
  mitchll1214/url-destroyer:latest
```

#### docker run（MySQL）

```bash
docker run -d \
  --name url-destroyer \
  -p 8087:80 \
  -e DB_DRIVER=mysql \
  -e DB_HOST=mysql.example.com \
  -e DB_PORT=3306 \
  -e DB_DATABASE=url_destroyer \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=your-db-password \
  -e DB_TABLE_PREFIX=ud_ \
  -e ADMIN_PASSWORD=my-secret-password \
  mitchll1214/url-destroyer:latest
```

> 💡 MySQL 模式下首次启动将自动创建所有带前缀的数据表。

#### docker-compose（含 MySQL 配置）

```yaml
services:
  app:
    image: mitchll1214/url-destroyer:latest
    ports:
      - "8087:80"
    volumes:
      - ./data:/var/www/data     # SQLite 模式需要；MySQL 可选
    environment:
      - ADMIN_PASSWORD=my-secret-password
      - DEFAULT_ACCESS_TIMEOUT=48
      - DEFAULT_ABSOLUTE_EXPIRY_HOURS=336
      - BASE_URL=https://links.example.com
      - DB_TABLE_PREFIX=ud_
      # MySQL 模式（取消注释以切换）
      # - DB_DRIVER=mysql
      # - DB_HOST=mysql-container
      # - DB_PORT=3306
      # - DB_DATABASE=url_destroyer
      # - DB_USERNAME=root
      # - DB_PASSWORD=your-db-password
```

### 数据持久化

**SQLite**：数据库文件 `app.db` 存放在挂载的 `/var/www/data` 目录中。确保该目录映射到宿主机固定路径，即使删除容器、更新镜像，数据也不会丢失。

**MySQL**：数据存储在外部 MySQL 服务器中，升级镜像不会影响数据。无需挂载数据卷。

> ⚠️ 更新镜像时**不要**使用 `docker compose down -v`，`-v` 会删除数据卷。正确流程：
> ```bash
> docker compose pull && docker compose up -d
> ```

## 📊 数据库

> 📌 默认表名前缀为 `ud_`，可通过 `DB_TABLE_PREFIX` 环境变量修改。
> 下表以默认前缀为例，实际表名 = `{DB_TABLE_PREFIX}{表名}`（如 `ud_links`）。

### ud_links

| 字段 | 类型 | 说明 |
|---|---|---|
| id | INTEGER | 主键 |
| token | TEXT | 32 位 hex 唯一标识 |
| campaign_name | TEXT | 活动名称 |
| target_content | TEXT | 表单 JSON 或静态 HTML |
| access_timeout | INTEGER | 首次访问后超时（秒） |
| absolute_expiry_hours | INTEGER | 绝对过期（小时） |
| max_accesses | INTEGER | 最大访问次数 |
| access_count | INTEGER | 已访问次数 |
| expire_on_submit | INTEGER | 提交后立刻失效开关 |
| status | TEXT | active / draft / submitted / expired |
| created_at | TEXT | 创建时间 |
| first_accessed_at | TEXT | 首次访问时间 |
| expires_at | TEXT | 过期时间 |

### ud_access_logs

| 字段 | 类型 | 说明 |
|---|---|---|
| id | INTEGER | 主键 |
| link_id | INTEGER | 外键 → ud_links.id |
| ip | TEXT | 访问者 IP |
| user_agent | TEXT | 浏览器 UA |
| referer | TEXT | 来源页面 |
| form_data | TEXT | 提交的表单数据（JSON） |
| accessed_at | TEXT | 访问时间 |

### ud_form_drafts

| 字段 | 类型 | 说明 |
|---|---|---|
| token | TEXT | 链接 Token（主键） |
| form_data | TEXT | 草稿表单数据（JSON） |
| updated_at | TEXT | 最后更新时间 |

### ud_login_attempts

| 字段 | 类型 | 说明 |
|---|---|---|
| id | INTEGER | 主键 |
| ip | TEXT | 尝试登录的 IP |
| attempted_at | TEXT | 尝试时间 |

## 📄 License

MIT © [Mitchll1214](https://github.com/Mitchll1214)

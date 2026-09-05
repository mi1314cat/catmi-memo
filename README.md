# Catmi Memo

Memos 风格的轻量个人碎片记录站。纯 PHP + MySQL + 原生 HTML/CSS/JS，无需命令行、
无需 Docker / Node.js / Composer / 常驻进程——上传到任何支持 PHP + MySQL 的虚拟主机即可运行，
浏览器里完成安装向导就能开始使用。

> 记录想法、收藏瞬间、贴上图片和视频；每条内容可以公开，也可以仅自己可见。

## 主要功能

- **动态发布**：文字 + 图片（多图九宫格 / Lightbox 放大）+ 小视频（MP4/WebM 原生播放器）+ 小文件（下载卡片）
- **可见性控制**：每条动态可选「🔒 仅自己可见」（默认）或「🌐 公开」，在数据库查询层强制过滤，不是前端隐藏
- **附件权限网关**：所有图片和附件经 `media.php` 鉴权后输出，私密附件拿到 URL 也无法访问
- **用户头像**：上传 / 更换 / 删除，服务端自动裁剪为正方形，无头像显示首字母色块
- **邀请制注册**：管理员签发邀请码（随机码 / 自定义码、单次 / 多次、可选有效期、可停用），防并发抢码
- **管理后台**：用户管理（禁用 / 启用 / 删除）、邀请码管理、内容管理、网站设置（背景图、游客浏览开关）
- **个性化**：管理员可上传自定义背景图片，前台毛玻璃卡片自动适配
- **响应式**：桌面左侧边栏，移动端顶栏 + 底部标签栏

## 技术栈

- PHP 7.4+（推荐 8.x）+ MySQL 5.7+ / MariaDB 10.2+
- 前端为原生 HTML/CSS/JS，无任何 CDN、npm 包或构建步骤
- 无 Composer 依赖、无框架、无常驻进程；所有第三方依赖为零

## 系统要求

| 项目 | 要求 |
|------|------|
| PHP | 7.4+，必需扩展：`pdo_mysql`、`mbstring`、`session`、`fileinfo`；可选 `gd`（有则头像自动裁剪） |
| 数据库 | MySQL 5.7+ / MariaDB 10.2+，InnoDB，utf8mb4 |
| Web 服务器 | Apache（需支持 `.htaccess`）或等效配置的 Nginx |
| 磁盘 | 依赖上传限制，建议 ≥ 100MB 可写空间 |

## 快速部署（约 5 分钟）

1. **上传项目**：把本仓库全部文件上传到网站根目录（如 `htdocs/`、`public_html/`）
2. **创建数据库**：在主机控制面板创建一个 MySQL 数据库，记下主机 / 库名 / 用户名 / 密码
3. **填写配置**：复制 `config/config.example.php` 为 `config/config.php`，填入数据库信息
4. **运行安装向导**：浏览器访问 `https://你的域名/install.php`
   - 向导会自动建表（`database/schema.sql`，幂等）
   - 设置**管理员用户名和密码**（密码以 `password_hash()` 散列存储）
   - 完成后自动锁定，**请再手动删除服务器上的 `install.php`**
5. **开始使用**：访问首页登录管理员 → 到「管理后台 → 邀请码」创建一个邀请码 → 退出登录 →
   用邀请码注册日常使用的个人账号（推荐：管理员账号只做管理，日常记录用普通账号）

> 没有 SSH 也能完成全部步骤；上传用主机面板的文件管理器或 FTP 均可。

## 手动安装（可选，不用向导）

1. 按「快速部署」第 1-3 步上传文件并配置
2. 用 phpMyAdmin（或任何 MySQL 客户端）导入 `database/schema.sql`
3. 手动创建管理员（密码必须先在本地用 PHP 生成散列）：
   ```sql
   INSERT INTO users (username, password_hash, role)
   VALUES ('你的用户名', '<password_hash("你的密码") 的输出>', 'admin');
   ```
   或直接跳过，改用安装向导创建。
4. 确认 `config/installed.lock` 不存在（避免与向导冲突）；无论哪种方式，装完都应删除 `install.php`

## 配置说明（config/config.php）

| 配置项 | 说明 |
|--------|------|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | MySQL 连接信息（主机面板可查） |
| `MAX_IMAGE_SIZE` | 单张图片上限，默认 5MB |
| `MAX_VIDEO_SIZE` | 单个视频上限（MP4/WebM），默认 8MB |
| `MAX_FILE_SIZE` | 单个文件附件上限，默认 8MB |
| `MAX_AVATAR_SIZE` | 头像上限，默认 2MB |
| `MAX_IMAGES_PER_POST` | 每条动态最多图片数，默认 9 |
| `MAX_ATTACHMENTS_PER_POST` | 每条动态最多附件数（视频+文件合计），默认 5 |

所有大小限制会自动与主机的 PHP `upload_max_filesize` / `post_max_size` 取较小值生效：
配置写大不会报错，只会按有效值拒绝并提示。共享主机通常限制单文件 8-20MB，按实际能力调整即可。

配置与代码完全分离：`config/config.php` 是唯一的环境配置文件（已被 `.htaccess` 保护，
浏览器无法访问），程序代码不包含任何主机特定的地址或凭据。

## 邀请码系统

- 管理员登录 → 管理后台 → 邀请码 → 创建：
  - **自定义码**（可空 = 自动生成随机码，形如 `CATMI-XXXXXXXX`）
  - **可用次数**（1 = 一次性；N = 可邀请 N 人）
  - **有效天数**（0 = 永久）
- 邀请码可随时停用 / 启用 / 删除；列表显示已用次数与剩余次数
- 注册页必须填写有效且未停用、未过期的邀请码才能完成注册
- 消费为原子 `UPDATE`，天然防止并发抢码

## 背景图片设置

- 方式一：管理员 → 管理后台 → 网站设置 → 直接上传图片（自动存到 `assets/bg/`，立即生效，页面带预览）
- 方式二：FTP 上传图片到 `assets/`，然后在设置里填入路径（如 `assets/background.jpg`，带不带开头的 `/` 均可）
- 留空并保存 = 恢复默认渐变背景
- 渲染方式：固定定位底层图 + 轻遮罩 + 毛玻璃卡片，任何壁纸下文字都可读；
  已规避 iOS Safari 的 `background-attachment: fixed` 渲染问题

## 图片 / 附件 / 头像上传

| 类型 | 格式 | 默认上限 | 说明 |
|------|------|----------|------|
| 图片 | JPG / PNG / GIF / WebP | 5MB × 9 张 | 九宫格展示，点击放大 |
| 视频 | MP4 / WebM | 8MB × 5 | 原生播放器，不自动播放，支持拖动进度 |
| 文件 | PDF / TXT / CSV / ZIP | 8MB × 5 | 显示名称 + 大小 + 下载按钮 |
| 头像 | JPG / PNG / WebP | 2MB | 服务端裁剪为 256px 正方形 |

安全机制：前端选文件即校验大小；服务端 `getimagesize()` / `finfo` 检测**真实内容**
（改扩展名无法绕过）；MIME 白名单之外一律拒绝（PHP / HTML / JS / SVG / EXE / BAT / SH 等
可执行类型永远不可能通过）；存储使用随机文件名；`uploads/` 整目录禁止直接访问与执行。

## 目录结构

```
catmi-memos/
├── README.md               本文件
├── LICENSE                 MIT 许可证
├── .gitignore
├── install.php             安装向导（首次部署用，装完删除）
├── index.php               首页（发布框 + 最近动态 / 访客欢迎页）
├── public.php              公开动态时间线
├── my.php                  我的内容（全部 / 公开 / 私密）
├── profile.php             账户资料（头像 / 改密 / 退出）
├── login.php / register.php / logout.php
├── post.php / edit.php / delete.php
├── media.php               图片与附件统一权限网关（视频 Range 流式 / 文件下载）
├── .htaccess               根目录安全规则（拦截配置 / SQL / 日志等）
├── admin/                  管理后台（总览 / 用户 / 邀请码 / 内容 / 设置）
├── includes/               核心库（会话与权限 / 数据库 / 工具函数 / CSRF / 页头页脚）
├── assets/
│   ├── css/  js/           样式与脚本（无第三方依赖）
│   ├── bg/                 后台上传的背景图（运行时生成）
│   └── avatars/            用户头像（运行时生成）
├── config/
│   ├── config.example.php  配置模板（复制为 config.php 后填写）
│   └── .htaccess           整目录 403
├── database/
│   └── schema.sql          完整建表脚本（幂等，可重复导入）
└── uploads/                用户上传目录（运行时生成；整目录禁止直接访问）
```

## 安全说明

- 所有 SQL 使用 PDO prepared statements（禁用模拟预处理）；所有输出经 `htmlspecialchars` 转义
- 所有 POST 请求校验 CSRF token；登录失败统一话术，不泄露账号是否存在
- Session 使用 HttpOnly + SameSite=Lax（HTTPS 下自动加 Secure）；登录成功后重置会话 ID
- 可见性在数据库查询层过滤；图片与附件逐个鉴权；私密内容对管理员可见仅限管理需要
- 被禁用用户的会话立即失效（每次请求核对账号状态）
- 管理员不能禁用 / 删除自己或其他管理员（防锁死）
- 上传目录 `.htaccess` 拒绝一切直接访问；配置目录 403；SQL / 日志 / 备份文件名全部 403
- 删除用户 / 动态会级联清理其图片、附件与头像文件

## 常见问题

**Q：安装向导提示「无法连接数据库」？**
检查 `config/config.php` 四项是否与主机面板一致；部分主机要求 DB_HOST 用内网地址而非 localhost。

**Q：上传图片报「超过服务器上传限制」？**
主机 PHP 的 `upload_max_filesize` / `post_max_size` 较小。可在 `php.ini` 或 `.user.ini` 调大，
或把 `config/config.php` 里的对应限制改小到主机允许的值。

**Q：视频上传成功但播放不了？**
确认文件是标准 MP4（H.264）或 WebM；浏览器不支持的编码会无法解码（本程序不做转码，保持轻量）。
另外本程序已支持 HTTP Range，iOS Safari 可正常播放。

**Q：头像上传后没有变成圆形？**
服务器缺少 GD 扩展时保留原图，由前端 `object-fit: cover` 裁圆显示——效果一致，仅文件稍大。

**Q：忘了管理员密码？**
用 phpMyAdmin 执行：`UPDATE users SET password_hash='<password_hash("新密码") 的输出>' WHERE username='管理员用户名';`

**Q：想换域名 / 迁移主机？**
整个项目不绑定域名。把文件与数据库搬到新主机、改 `config/config.php` 即可；已上传的图片附件一并拷贝。

**Q：注册页提示邀请码无效？**
邀请码停用 / 过期 / 次数用尽都会被拒。到管理后台确认状态，或新建一个。

## 升级方法

1. 备份数据库（导出 SQL）与 `uploads/`、`assets/` 目录
2. 用新版本文件覆盖旧文件（**不要覆盖** `config/config.php`、`uploads/`、`assets/bg/`、`assets/avatars/`）
3. 若新版 `database/schema.sql` 有结构变更，按版本说明执行对应 `ALTER` 语句（从不 DROP 表）
4. 访问首页确认正常；CSS/JS 带版本号参数，改版后无需手动清浏览器缓存

## 卸载方法

1. 备份需要保留的数据（导出 SQL、下载 `uploads/` 与 `assets/`）
2. 删除网站目录全部文件
3. 在 phpMyAdmin 删除该数据库（`DROP DATABASE`）或其中的 6 张表
4. 删除主机面板上的数据库用户（可选）

## 许可证

[MIT](LICENSE) —— 可自由使用、修改、二次分发（包括商用），请保留许可证文件。

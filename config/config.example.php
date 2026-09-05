<?php
/**
 * 数据库配置示例文件。
 *
 * 使用方法：
 *   1. 复制本文件为 config.php（与本文件同目录）
 *   2. 把下面四项换成你主机面板「MySQL」区显示的真实信息
 *
 * 安全说明：
 *   - config.php 已被 .htaccess 保护，浏览器无法直接访问
 *   - 绝对不要把真实密码提交到 Git 或写进其他代码
 *
 * 上传限制说明：
 *   - 下面的大小/数量限制均可按主机能力调整
 *   - 程序会自动取「配置值」与「PHP upload_max_filesize / post_max_size」中较小者，
 *     所以写大了也不会突破主机限制（不会 500，只会按有效值拒绝并提示）
 *   - 不要假设主机一定允许某个值，先用 phpinfo() 或控制面板确认
 */
return [
    'DB_HOST' => 'localhost',           // 数据库主机（主机面板 MySQL 区显示；共享主机常用内网地址）
    'DB_NAME' => 'example_database',    // 数据库名
    'DB_USER' => 'example_user',        // 数据库用户名
    'DB_PASS' => 'CHANGE_ME_PASSWORD',  // 数据库密码

    /* ---- 上传限制（字节；会与 PHP ini 自动取小） ---- */
    'MAX_IMAGE_SIZE'           => 5242880,  // 单张图片上限，默认 5MB
    'MAX_VIDEO_SIZE'           => 8388608,  // 单个视频上限（mp4/webm），默认 8MB
    'MAX_FILE_SIZE'            => 8388608,  // 单个文件附件上限，默认 8MB
    'MAX_AVATAR_SIZE'          => 2097152,  // 头像上限，默认 2MB
    'MAX_IMAGES_PER_POST'      => 9,        // 每条动态最多图片数
    'MAX_ATTACHMENTS_PER_POST' => 5,        // 每条动态最多附件数（视频+文件合计）
];

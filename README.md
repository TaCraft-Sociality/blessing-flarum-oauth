# blessing-flarum-oauth
Blessing Skin 6 插件：用 TCS 社区（Flarum）账号登录皮肤站。

配套的 Flarum 插件是 `tacraft-sociality/flarum-server-oauth`。

## 安装

把 `tcs-login` 目录放到 Blessing Skin 的 `plugins/` 下，然后在后台启用「TCS社区登录」。

## 配置

在后台插件设置里填写：

如果保存后设置看起来被清空，执行 `php artisan options:cache` 即可

- **社区地址**：如 `https://forum.example.com`，结尾不要带斜杠
- **客户端 ID**、**客户端密钥**：Flarum 后台「Server OAuth」里创建的应用

把设置页显示的回调地址：

```
https://皮肤站/auth/tcs/callback
```

完整填入 Flarum 后台该应用的 **回调地址 / Redirect URI**。

Flarum 端还需要：

- 应用授权类型包含 `authorization_code`
- 应用权限包含 `user.read`
- 在「权限」页给对应用户组开启 **Use OAuth**

## 显示位置

登录页和注册页都会显示「TCS社区」按钮：

- 登录页：密码登录按钮下方、其它登录方式上方
- 注册页：注册按钮下方、其它登录方式上方

插件不依赖 `oauth` 基础插件；安装了 `oauth` 时，「TCS社区」按钮会显示在「其它登录方式」标题上方。

## 登录流程

1. 点击「TCS社区」
2. 跳转到 Flarum 授权页
3. 授权后回到 `/auth/tcs/callback`
4. 用社区邮箱匹配皮肤站账号；没有则自动注册
5. 登录成功，回到进入登录前的页面

## 许可

MIT


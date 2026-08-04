using FloFi.Services;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Data.SqlClient;

namespace FloFi.Pages;

public class IndexModel : PageModelBase
{
    private readonly MailService _mail;

    public IndexModel(DbService db, IConfiguration config, MailService mail) : base(db, config)
        => _mail = mail;

    public async Task<IActionResult> OnGetAsync()
    {
        if (await GetCurrentUserAsync() != null) return RedirectToPage("/Dashboard");
        ViewData["Title"] = "Welcome";
        return Page();
    }

    public async Task<IActionResult> OnPostLoginAsync(string username, string password, bool remember)
    {
        var user = await Db.GetAccountByUsernameAsync(username ?? "");
        if (user == null || !BCrypt.Net.BCrypt.Verify(password ?? "", user.PasswordHash))
        { TempData["Error"] = "Invalid username or password."; TempData["Tab"] = "login"; return RedirectToPage(); }
        if (user.IsDisabled)
        { TempData["Error"] = "This account has been disabled."; TempData["Tab"] = "login"; return RedirectToPage(); }

        HttpContext.Session.SetInt32("uid", user.Id);

        if (remember)
        {
            var token = RandomHex();
            var exp = DateTime.UtcNow.AddDays(30);
            await Db.SetRememberTokenAsync(user.Id, token, exp);
            Response.Cookies.Append("remember_me", token, new CookieOptions
            {
                Expires = DateTimeOffset.UtcNow.AddDays(30),
                HttpOnly = true,
                Secure = Request.IsHttps,
                SameSite = SameSiteMode.Lax
            });
        }

        return user.MustResetPassword
            ? RedirectToPage("/Reset", new { force = 1 })
            : RedirectToPage("/Dashboard");
    }

    public async Task<IActionResult> OnPostSignupAsync(
        string username, string fullName, string email, string? phone,
        string country, string password, string password2)
    {
        if (string.IsNullOrWhiteSpace(username) || string.IsNullOrWhiteSpace(fullName) ||
            string.IsNullOrWhiteSpace(email) || string.IsNullOrWhiteSpace(password))
        { TempData["Error"] = "All fields are required."; TempData["Tab"] = "signup"; return RedirectToPage(); }
        if ((password ?? "").Length < 8)
        { TempData["Error"] = "Password must be at least 8 characters."; TempData["Tab"] = "signup"; return RedirectToPage(); }
        if (password != password2)
        { TempData["Error"] = "Passwords do not match."; TempData["Tab"] = "signup"; return RedirectToPage(); }

        try
        {
            var hash = BCrypt.Net.BCrypt.HashPassword(password);
            var uid = await Db.CreateAccountAsync(username, fullName, email, phone, country ?? "US", hash);
            HttpContext.Session.SetInt32("uid", uid);
            TempData["Flash"] = $"Welcome to FloFi, {fullName}!";
            return RedirectToPage("/Dashboard");
        }
        catch (SqlException ex) when (ex.Number == 2627 || ex.Number == 2601)
        { TempData["Error"] = "That username or email is already taken."; TempData["Tab"] = "signup"; return RedirectToPage(); }
    }

    public async Task<IActionResult> OnPostLogoutAsync()
    {
        var token = Request.Cookies["remember_me"];
        if (!string.IsNullOrEmpty(token))
        {
            await Db.DeleteRememberTokenAsync(token);
            Response.Cookies.Delete("remember_me");
        }
        HttpContext.Session.Clear();
        return RedirectToPage();
    }
}

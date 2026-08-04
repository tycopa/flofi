using FloFi.Services;
using Microsoft.AspNetCore.Mvc;

namespace FloFi.Pages;

public class ResetModel : PageModelBase
{
    private readonly MailService _mail;

    public bool IsForced { get; private set; }
    public string? Token { get; private set; }

    public ResetModel(DbService db, IConfiguration config, MailService mail) : base(db, config)
        => _mail = mail;

    public async Task<IActionResult> OnGetAsync(string? token, int? force)
    {
        var user = await GetCurrentUserAsync();
        IsForced = force == 1 && user != null;
        Token = token;
        ViewData["Title"] = "Reset Password";
        return Page();
    }

    public async Task<IActionResult> OnPostRequestAsync(string email)
    {
        var id = await Db.GetAccountIdByEmailAsync(email ?? "");
        if (id.HasValue)
        {
            var name = await Db.GetAccountFullNameByEmailAsync(email!) ?? "";
            var token = RandomHex();
            await Db.UpsertPasswordResetAsync(id.Value, token, DateTime.UtcNow.AddHours(1));
            var link = $"{Request.Scheme}://{Request.Host}/Reset?token={Uri.EscapeDataString(token)}";
            await _mail.SendAsync(email!, name, $"{AppName} — Password Reset",
                $"<p>Hi {name},</p><p>Click below to reset your password (expires in 1 hour):</p><p><a href=\"{link}\">{link}</a></p>");
        }
        TempData["Flash"] = "If that email is in our system, a reset link has been sent.";
        return RedirectToPage();
    }

    public async Task<IActionResult> OnPostDoResetAsync(string token, string newPassword, string confirmPassword)
    {
        if (newPassword.Length < 8) { TempData["Error"] = "Password must be at least 8 characters."; Token = token; return Page(); }
        if (newPassword != confirmPassword) { TempData["Error"] = "Passwords do not match."; Token = token; return Page(); }

        var uid = await Db.ValidateResetTokenAsync(token);
        if (!uid.HasValue) { TempData["Error"] = "Invalid or expired reset link."; return Page(); }

        await Db.UpdatePasswordAsync(uid.Value, BCrypt.Net.BCrypt.HashPassword(newPassword));
        await Db.DeletePasswordResetsByAccountAsync(uid.Value);
        HttpContext.Session.Clear();
        TempData["Flash"] = "Password updated. Please sign in.";
        return RedirectToPage("/Index");
    }

    public async Task<IActionResult> OnPostForceResetAsync(string newPassword, string confirmPassword)
    {
        var user = await GetCurrentUserAsync();
        if (user == null) return RedirectToPage("/Index");
        if (newPassword.Length < 8) { TempData["Error"] = "Password must be at least 8 characters."; IsForced = true; return Page(); }
        if (newPassword != confirmPassword) { TempData["Error"] = "Passwords do not match."; IsForced = true; return Page(); }

        await Db.UpdatePasswordAsync(user.Id, BCrypt.Net.BCrypt.HashPassword(newPassword));
        TempData["Flash"] = "Password updated.";
        return RedirectToPage("/Dashboard");
    }
}

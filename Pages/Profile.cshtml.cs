using FloFi.Services;
using Microsoft.AspNetCore.Mvc;

namespace FloFi.Pages;

public class ProfileModel : PageModelBase
{
    public static readonly string[] Themes = ["orange", "red", "blue", "green", "purple", "gray", "black"];

    public ProfileModel(DbService db, IConfiguration config) : base(db, config) { }

    public async Task<IActionResult> OnGetAsync()
    {
        if (await RequireLoginAsync() == null) return Page();
        ViewData["Title"] = "Profile";
        return Page();
    }

    public async Task<IActionResult> OnPostProfileAsync(string fullName, string email, string? phone, string country, string theme)
    {
        if (await RequireLoginAsync() == null) return Page();
        if (string.IsNullOrWhiteSpace(fullName) || string.IsNullOrWhiteSpace(email))
        { TempData["Error"] = "Full name and email are required."; return RedirectToPage(); }

        try
        {
            await Db.UpdateAccountProfileAsync(Me!.Id, fullName, email, phone, country, theme);
            TempData["Flash"] = "Profile updated.";
        }
        catch { TempData["Error"] = "Update failed. Email may already be in use."; }
        return RedirectToPage();
    }

    public async Task<IActionResult> OnPostPasswordAsync(string currentPassword, string newPassword, string confirmPassword)
    {
        if (await RequireLoginAsync() == null) return Page();
        var user = await Db.GetAccountByIdAsync(Me!.Id);
        if (user == null || !BCrypt.Net.BCrypt.Verify(currentPassword, user.PasswordHash))
        { TempData["Error"] = "Current password is incorrect."; return RedirectToPage(); }
        if (newPassword.Length < 8)
        { TempData["Error"] = "New password must be at least 8 characters."; return RedirectToPage(); }
        if (newPassword != confirmPassword)
        { TempData["Error"] = "New passwords do not match."; return RedirectToPage(); }

        await Db.UpdatePasswordAsync(Me!.Id, BCrypt.Net.BCrypt.HashPassword(newPassword));
        TempData["Flash"] = "Password changed successfully.";
        return RedirectToPage();
    }
}

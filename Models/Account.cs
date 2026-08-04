namespace FloFi.Models;

public class Account
{
    public int Id { get; set; }
    public string Username { get; set; } = "";
    public string FullName { get; set; } = "";
    public string Email { get; set; } = "";
    public string? Phone { get; set; }
    public string Country { get; set; } = "US";
    public string PasswordHash { get; set; } = "";
    public bool IsAdmin { get; set; }
    public bool IsDisabled { get; set; }
    public string Theme { get; set; } = "orange";
    public bool MustResetPassword { get; set; }
    public DateTime? ExpiresAt { get; set; }   // from remember_tokens join
}

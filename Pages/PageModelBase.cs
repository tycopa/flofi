using System.Security.Cryptography;
using FloFi.Models;
using FloFi.Services;
using Microsoft.AspNetCore.Mvc.RazorPages;

namespace FloFi.Pages;

public abstract class PageModelBase : PageModel
{
    protected readonly DbService Db;
    protected readonly IConfiguration Config;

    public Account? Me { get; private set; }
    public string Theme { get; private set; } = "#cc5500";
    public string ThemeDk { get; private set; } = "#9e3f00";
    public string AppName => Config["AppName"] ?? "FloFi";

    protected PageModelBase(DbService db, IConfiguration config)
    {
        Db = db;
        Config = config;
    }

    protected async Task<Account?> GetCurrentUserAsync()
    {
        var uid = HttpContext.Session.GetInt32("uid");
        if (uid.HasValue)
        {
            var user = await Db.GetAccountByIdAsync(uid.Value);
            if (user != null && !user.IsDisabled) { ApplyUser(user); return user; }
        }

        var token = Request.Cookies["remember_me"];
        if (!string.IsNullOrEmpty(token))
        {
            var user = await Db.GetAccountByRememberTokenAsync(token);
            if (user != null && !user.IsDisabled)
            {
                if (user.ExpiresAt > DateTime.UtcNow)
                {
                    HttpContext.Session.SetInt32("uid", user.Id);
                    ApplyUser(user);
                    return user;
                }
                await Db.DeleteRememberTokenAsync(token);
                Response.Cookies.Delete("remember_me");
            }
        }
        return null;
    }

    protected async Task<Account?> RequireLoginAsync()
    {
        var user = await GetCurrentUserAsync();
        if (user == null) Response.Redirect("/");
        return user;
    }

    protected async Task<Account?> RequireAdminAsync()
    {
        var user = await RequireLoginAsync();
        if (user != null && !user.IsAdmin) Response.Redirect("/Dashboard");
        return user?.IsAdmin == true ? user : null;
    }

    private void ApplyUser(Account user)
    {
        Me = user;
        (Theme, ThemeDk) = user.Theme switch
        {
            "red"    => ("#c62828", "#8e0000"),
            "blue"   => ("#1e88e5", "#1565c0"),
            "green"  => ("#2e7d32", "#1b5e20"),
            "purple" => ("#7e57c2", "#5e35b1"),
            "gray"   => ("#757575", "#424242"),
            "black"  => ("#111111", "#000000"),
            _        => ("#cc5500", "#9e3f00"),
        };
        ViewData["Theme"] = Theme;
        ViewData["ThemeDk"] = ThemeDk;
        ViewData["Me"] = user;
    }

    protected static string Dollars(int cents) => "$" + (cents / 100m).ToString("0.00");

    protected static string RandomHex(int bytes = 32) =>
        Convert.ToHexString(RandomNumberGenerator.GetBytes(bytes)).ToLower();

    public static readonly IReadOnlyDictionary<string, string> Countries =
        new Dictionary<string, string>
        {
            ["US"] = "🇺🇸 United States (+1)",
            ["CA"] = "🇨🇦 Canada (+1)",
            ["GB"] = "🇬🇧 United Kingdom (+44)",
            ["AU"] = "🇦🇺 Australia (+61)",
            ["DE"] = "🇩🇪 Germany (+49)",
            ["FR"] = "🇫🇷 France (+33)",
            ["IT"] = "🇮🇹 Italy (+39)",
            ["ES"] = "🇪🇸 Spain (+34)",
            ["MX"] = "🇲🇽 Mexico (+52)",
            ["BR"] = "🇧🇷 Brazil (+55)",
            ["IN"] = "🇮🇳 India (+91)",
            ["JP"] = "🇯🇵 Japan (+81)",
            ["CN"] = "🇨🇳 China (+86)",
            ["KR"] = "🇰🇷 South Korea (+82)",
            ["NL"] = "🇳🇱 Netherlands (+31)",
            ["SE"] = "🇸🇪 Sweden (+46)",
            ["NO"] = "🇳🇴 Norway (+47)",
            ["DK"] = "🇩🇰 Denmark (+45)",
            ["FI"] = "🇫🇮 Finland (+358)",
            ["CH"] = "🇨🇭 Switzerland (+41)",
            ["AT"] = "🇦🇹 Austria (+43)",
            ["BE"] = "🇧🇪 Belgium (+32)",
            ["PL"] = "🇵🇱 Poland (+48)",
            ["PT"] = "🇵🇹 Portugal (+351)",
            ["NZ"] = "🇳🇿 New Zealand (+64)",
            ["ZA"] = "🇿🇦 South Africa (+27)",
            ["SG"] = "🇸🇬 Singapore (+65)",
            ["IE"] = "🇮🇪 Ireland (+353)",
            ["AR"] = "🇦🇷 Argentina (+54)",
            ["CL"] = "🇨🇱 Chile (+56)",
            ["TR"] = "🇹🇷 Turkey (+90)",
            ["SA"] = "🇸🇦 Saudi Arabia (+966)",
            ["AE"] = "🇦🇪 UAE (+971)",
            ["RU"] = "🇷🇺 Russia (+7)",
            ["UA"] = "🇺🇦 Ukraine (+380)",
        };
}

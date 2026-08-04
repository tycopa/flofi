using FloFi.Models;
using FloFi.Services;
using Microsoft.AspNetCore.Mvc;
using Npgsql;

namespace FloFi.Pages;

public class CategoriesModel : PageModelBase
{
    public IEnumerable<Category> Cats { get; private set; } = [];

    public CategoriesModel(DbService db, IConfiguration config) : base(db, config) { }

    public async Task<IActionResult> OnGetAsync()
    {
        if (await RequireLoginAsync() == null) return Page();
        await LoadAsync();
        return Page();
    }

    public async Task<IActionResult> OnPostAddAsync(string type, string name)
    {
        if (await RequireLoginAsync() == null) return Page();
        if (!new[] { "revenue", "expense" }.Contains(type))
        { TempData["Error"] = "Invalid type."; return RedirectToPage(); }
        name = name?.Trim() ?? "";
        if (name == "") { TempData["Error"] = "Category name required."; return RedirectToPage(); }

        try
        {
            await Db.AddCategoryAsync(Me!.Id, type, name);
            TempData["Flash"] = $"Category <b>{name}</b> added to <b>{System.Globalization.CultureInfo.CurrentCulture.TextInfo.ToTitleCase(type)}</b>.";
        }
        catch (PostgresException ex) when (ex.SqlState == "23505")
        { TempData["Error"] = $"⚠️ Category <b>{name}</b> already exists under <b>{System.Globalization.CultureInfo.CurrentCulture.TextInfo.ToTitleCase(type)}</b>."; }

        return RedirectToPage();
    }

    public async Task<IActionResult> OnPostDeleteAsync(int id)
    {
        if (await RequireLoginAsync() == null) return Page();
        await Db.DeleteCategoryAsync(id, Me!.Id);
        TempData["Flash"] = "Category deleted.";
        return RedirectToPage();
    }

    private async Task LoadAsync()
    {
        Cats = await Db.GetCategoriesAsync(Me!.Id);
        ViewData["Title"] = "Categories";
    }
}

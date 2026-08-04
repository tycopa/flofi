using FloFi.Services;
using Microsoft.AspNetCore.Mvc;

namespace FloFi.Pages;

public class RecapModel : PageModelBase
{
    public int Year { get; private set; }
    public Dictionary<int, (long Revenue, long Expense)> ByMonth { get; private set; } = [];
    public IEnumerable<dynamic> CatRows { get; private set; } = [];
    public static readonly string[] MonthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

    public RecapModel(DbService db, IConfiguration config) : base(db, config) { }

    public async Task<IActionResult> OnGetAsync(int? year)
    {
        if (await RequireLoginAsync() == null) return Page();

        Year = year ?? DateTime.Now.Year;
        ViewData["Title"] = $"Recap {Year}";

        var monthly = await Db.GetMonthlyRecapAsync(Me!.Id, Year);
        foreach (var row in monthly)
        {
            int m = int.Parse((string)row.mon);
            ByMonth[m] = ((long)(row.revenue ?? 0), (long)(row.expense ?? 0));
        }

        CatRows = await Db.GetCategoryRecapAsync(Me!.Id, Year);
        return Page();
    }
}

using FloFi.Models;
using FloFi.Services;
using Microsoft.AspNetCore.Mvc;

namespace FloFi.Pages;

public class DashboardModel : PageModelBase
{
    public IEnumerable<Transaction> Transactions { get; private set; } = [];
    public IEnumerable<Category> RevCats { get; private set; } = [];
    public IEnumerable<Category> ExpCats { get; private set; } = [];
    public int TotalRevenue { get; private set; }
    public int TotalExpense { get; private set; }
    public int Net => TotalRevenue - TotalExpense;
    public string Month { get; private set; } = "";
    public string MonthLabel { get; private set; } = "";
    public string PrevMonth { get; private set; } = "";
    public string NextMonth { get; private set; } = "";

    public DashboardModel(DbService db, IConfiguration config) : base(db, config) { }

    public async Task<IActionResult> OnGetAsync(string? month)
    {
        if (await RequireLoginAsync() == null) return Page();
        await LoadAsync(month);
        return Page();
    }

    public async Task<IActionResult> OnPostAddTxAsync(string type, int categoryId, decimal amount, string? note, DateTime txDate)
    {
        if (await RequireLoginAsync() == null) return Page();
        if (!new[] { "revenue", "expense" }.Contains(type) || categoryId == 0 || amount <= 0)
        { TempData["Error"] = "Please fill in all required fields."; return RedirectToPage(); }

        await Db.AddTransactionAsync(Me!.Id, categoryId, type, (int)Math.Round(amount * 100), note, txDate);
        TempData["Flash"] = "Transaction added.";
        return RedirectToPage();
    }

    public async Task<IActionResult> OnPostDeleteTxAsync(int txId)
    {
        if (await RequireLoginAsync() == null) return Page();
        await Db.DeleteTransactionAsync(txId, Me!.Id);
        TempData["Flash"] = "Transaction deleted.";
        return RedirectToPage();
    }

    private async Task LoadAsync(string? month)
    {
        if (string.IsNullOrEmpty(month) || !System.Text.RegularExpressions.Regex.IsMatch(month, @"^\d{4}-\d{2}$"))
            month = DateTime.Now.ToString("yyyy-MM");
        Month = month;

        var dt = DateTime.ParseExact(month + "-01", "yyyy-MM-dd", null);
        PrevMonth = dt.AddMonths(-1).ToString("yyyy-MM");
        NextMonth = dt.AddMonths(1).ToString("yyyy-MM");
        MonthLabel = dt.ToString("MMMM yyyy");

        var cats = (await Db.GetCategoriesAsync(Me!.Id)).ToList();
        RevCats = cats.Where(c => c.Type == "revenue");
        ExpCats = cats.Where(c => c.Type == "expense");

        Transactions = await Db.GetTransactionsAsync(Me!.Id, month);
        foreach (var tx in Transactions)
        {
            if (tx.CatType == "revenue") TotalRevenue += tx.AmountCents;
            else TotalExpense += tx.AmountCents;
        }

        ViewData["Title"] = "Dashboard";
    }
}

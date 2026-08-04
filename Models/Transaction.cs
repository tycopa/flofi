namespace FloFi.Models;

public class Transaction
{
    public int Id { get; set; }
    public int AccountId { get; set; }
    public int CategoryId { get; set; }
    public string Type { get; set; } = "expense";
    public int AmountCents { get; set; }
    public string? Note { get; set; }
    public DateTime TxDate { get; set; }
    public string CatName { get; set; } = "";
    public string CatType { get; set; } = "";

    public string Dollars => "$" + (AmountCents / 100m).ToString("0.00");
}

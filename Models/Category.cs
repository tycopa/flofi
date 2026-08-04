namespace FloFi.Models;

public class Category
{
    public int Id { get; set; }
    public int AccountId { get; set; }
    public string Type { get; set; } = "expense";
    public string Name { get; set; } = "";
}

using Dapper;
using FloFi.Models;
using Microsoft.Data.SqlClient;

namespace FloFi.Services;

public class DbService
{
    private readonly string _cs;

    public DbService(IConfiguration config)
    {
        _cs = config.GetConnectionString("DefaultConnection")
              ?? throw new InvalidOperationException("ConnectionStrings:DefaultConnection not configured.");
    }

    private SqlConnection Conn() => new(_cs);

    public async Task<Account?> GetAccountByIdAsync(int id)
    {
        using var db = Conn();
        return await db.QueryFirstOrDefaultAsync<Account>(
            "SELECT id, username, full_name, email, phone, country, password_hash, is_admin, is_disabled, theme, must_reset_password FROM accounts WHERE id=@id",
            new { id });
    }

    public async Task<Account?> GetAccountByUsernameAsync(string username)
    {
        using var db = Conn();
        return await db.QueryFirstOrDefaultAsync<Account>(
            "SELECT TOP 1 id, username, full_name, email, phone, country, password_hash, is_admin, is_disabled, theme, must_reset_password FROM accounts WHERE username=@username",
            new { username });
    }

    public async Task<Account?> GetAccountByRememberTokenAsync(string token)
    {
        using var db = Conn();
        return await db.QueryFirstOrDefaultAsync<Account>(
            @"SELECT TOP 1 a.id, a.username, a.full_name, a.email, a.phone, a.country, a.password_hash,
                     a.is_admin, a.is_disabled, a.theme, a.must_reset_password, r.expires_at
              FROM remember_tokens r
              JOIN accounts a ON a.id = r.account_id
              WHERE r.token = @token",
            new { token });
    }

    public async Task<int> CreateAccountAsync(string username, string fullName, string email, string? phone, string country, string passwordHash)
    {
        using var db = Conn();
        return await db.ExecuteScalarAsync<int>(
            @"INSERT INTO accounts (username, full_name, email, phone, country, password_hash)
              VALUES (@username, @fullName, @email, @phone, @country, @passwordHash);
              SELECT CAST(SCOPE_IDENTITY() AS INT);",
            new { username, fullName, email, phone, country, passwordHash });
    }

    public async Task UpdateAccountProfileAsync(int id, string fullName, string email, string? phone, string country, string theme)
    {
        using var db = Conn();
        await db.ExecuteAsync(
            "UPDATE accounts SET full_name=@fullName, email=@email, phone=@phone, country=@country, theme=@theme WHERE id=@id",
            new { id, fullName, email, phone, country, theme });
    }

    public async Task UpdatePasswordAsync(int id, string passwordHash)
    {
        using var db = Conn();
        await db.ExecuteAsync(
            "UPDATE accounts SET password_hash=@passwordHash, must_reset_password=0 WHERE id=@id",
            new { id, passwordHash });
    }

    public async Task SetRememberTokenAsync(int accountId, string token, DateTime expiresAt)
    {
        using var db = Conn();
        await db.ExecuteAsync(
            "INSERT INTO remember_tokens (account_id, token, expires_at) VALUES (@accountId, @token, @expiresAt)",
            new { accountId, token, expiresAt });
    }

    public async Task DeleteRememberTokenAsync(string token)
    {
        using var db = Conn();
        await db.ExecuteAsync("DELETE FROM remember_tokens WHERE token=@token", new { token });
    }

    public async Task<IEnumerable<Category>> GetCategoriesAsync(int accountId)
    {
        using var db = Conn();
        return await db.QueryAsync<Category>(
            "SELECT * FROM categories WHERE account_id=@accountId ORDER BY type, name",
            new { accountId });
    }

    public async Task AddCategoryAsync(int accountId, string type, string name)
    {
        using var db = Conn();
        await db.ExecuteAsync(
            "INSERT INTO categories (account_id, type, name) VALUES (@accountId, @type, @name)",
            new { accountId, type, name });
    }

    public async Task DeleteCategoryAsync(int id, int accountId)
    {
        using var db = Conn();
        await db.ExecuteAsync(
            "DELETE FROM categories WHERE id=@id AND account_id=@accountId",
            new { id, accountId });
    }

    public async Task<IEnumerable<Transaction>> GetTransactionsAsync(int accountId, string month)
    {
        using var db = Conn();
        return await db.QueryAsync<Transaction>(
            @"SELECT t.id, t.account_id, t.category_id, t.type, t.amount_cents, t.note, t.tx_date,
                     c.name AS cat_name, c.type AS cat_type
              FROM transactions t
              JOIN categories c ON c.id = t.category_id
              WHERE t.account_id = @accountId AND FORMAT(t.tx_date, 'yyyy-MM') = @month
              ORDER BY t.tx_date DESC, t.id DESC",
            new { accountId, month });
    }

    public async Task AddTransactionAsync(int accountId, int categoryId, string type, int amountCents, string? note, DateTime txDate)
    {
        using var db = Conn();
        await db.ExecuteAsync(
            "INSERT INTO transactions (account_id, category_id, type, amount_cents, note, tx_date) VALUES (@accountId, @categoryId, @type, @amountCents, @note, @txDate)",
            new { accountId, categoryId, type, amountCents, note, txDate });
    }

    public async Task DeleteTransactionAsync(int id, int accountId)
    {
        using var db = Conn();
        await db.ExecuteAsync(
            "DELETE FROM transactions WHERE id=@id AND account_id=@accountId",
            new { id, accountId });
    }

    public async Task<IEnumerable<dynamic>> GetMonthlyRecapAsync(int accountId, int year)
    {
        using var db = Conn();
        return await db.QueryAsync(
            @"SELECT FORMAT(tx_date, 'MM') AS mon,
                     SUM(CASE WHEN c.type='revenue' THEN t.amount_cents ELSE 0 END) AS revenue,
                     SUM(CASE WHEN c.type='expense' THEN t.amount_cents ELSE 0 END) AS expense
              FROM transactions t
              JOIN categories c ON c.id = t.category_id
              WHERE t.account_id = @accountId AND YEAR(t.tx_date) = @year
              GROUP BY FORMAT(tx_date, 'MM')
              ORDER BY mon",
            new { accountId, year });
    }

    public async Task<IEnumerable<dynamic>> GetCategoryRecapAsync(int accountId, int year)
    {
        using var db = Conn();
        return await db.QueryAsync(
            @"SELECT c.name, c.type, SUM(t.amount_cents) AS total
              FROM transactions t
              JOIN categories c ON c.id = t.category_id
              WHERE t.account_id = @accountId AND YEAR(t.tx_date) = @year
              GROUP BY c.name, c.type ORDER BY c.type, total DESC",
            new { accountId, year });
    }

    public async Task<int?> GetAccountIdByEmailAsync(string email)
    {
        using var db = Conn();
        return await db.QueryFirstOrDefaultAsync<int?>(
            "SELECT TOP 1 id FROM accounts WHERE email=@email", new { email });
    }

    public async Task<string?> GetAccountFullNameByEmailAsync(string email)
    {
        using var db = Conn();
        return await db.QueryFirstOrDefaultAsync<string>(
            "SELECT TOP 1 full_name FROM accounts WHERE email=@email", new { email });
    }

    public async Task UpsertPasswordResetAsync(int accountId, string token, DateTime expiresAt)
    {
        using var db = Conn();
        await db.ExecuteAsync("DELETE FROM password_resets WHERE account_id=@accountId", new { accountId });
        await db.ExecuteAsync(
            "INSERT INTO password_resets (account_id, token, expires_at) VALUES (@accountId, @token, @expiresAt)",
            new { accountId, token, expiresAt });
    }

    public async Task<int?> ValidateResetTokenAsync(string token)
    {
        using var db = Conn();
        return await db.QueryFirstOrDefaultAsync<int?>(
            "SELECT TOP 1 account_id FROM password_resets WHERE token=@token AND expires_at > GETUTCDATE()",
            new { token });
    }

    public async Task DeletePasswordResetsByAccountAsync(int accountId)
    {
        using var db = Conn();
        await db.ExecuteAsync("DELETE FROM password_resets WHERE account_id=@accountId", new { accountId });
    }
}

using MailKit.Net.Smtp;
using MailKit.Security;
using MimeKit;

namespace FloFi.Services;

public class MailService
{
    private readonly IConfiguration _config;
    private readonly ILogger<MailService> _logger;

    public MailService(IConfiguration config, ILogger<MailService> logger)
    {
        _config = config;
        _logger = logger;
    }

    public async Task<bool> SendAsync(string to, string toName, string subject, string htmlBody)
    {
        try
        {
            var msg = new MimeMessage();
            msg.From.Add(new MailboxAddress(_config["AppName"] ?? "FloFi", _config["Mail:From"]));
            msg.To.Add(new MailboxAddress(toName, to));
            msg.Subject = subject;
            msg.Body = new TextPart("html") { Text = htmlBody };

            using var client = new SmtpClient();
            client.ServerCertificateValidationCallback = (s, c, h, e) => true;

            var secure = string.Equals(_config["Mail:Secure"], "tls", StringComparison.OrdinalIgnoreCase)
                ? SecureSocketOptions.StartTls
                : SecureSocketOptions.SslOnConnect;

            await client.ConnectAsync(_config["Mail:Host"], int.Parse(_config["Mail:Port"] ?? "465"), secure);
            await client.AuthenticateAsync(_config["Mail:Username"], _config["Mail:Password"]);
            await client.SendAsync(msg);
            await client.DisconnectAsync(true);
            return true;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Mail send failed to {To}", to);
            return false;
        }
    }
}

program PathFind;

uses
  Forms,
  Main in 'Main.pas' {MainForm};

begin
  Application.Initialize;
  Application.CreateForm(TMainForm, MainForm);
  Application.Run;
end.
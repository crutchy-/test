unit Main;

interface

uses
  Windows,
  SysUtils,
  Classes,
  Graphics,
  Controls,
  Forms,
  Dialogs,
  Sockets,
  StdCtrls,
  DateUtils,
  Unserializer,
  ComCtrls,
  Grids,
  ExtCtrls,
  ScktComp,
  Utils;

type

  TClientThread = class;

  TFormMain = class(TForm)
    PageControl1: TPageControl;
    TabSheet1: TTabSheet;
    TabSheet2: TTabSheet;
    ListBoxBuckets: TListBox;
    TabSheet3: TTabSheet;
    TabSheet4: TTabSheet;
    TabSheet5: TTabSheet;
    ListBoxExec: TListBox;
    Splitter2: TSplitter;
    Panel2: TPanel;
    ButtonSend: TButton;
    LabeledEditAliasesTrailing: TLabeledEdit;
    LabeledEditAliasesDest: TLabeledEdit;
    TabSheet6: TTabSheet;
    StatusBar1: TStatusBar;
    Timer1: TTimer;
    ProgressBar1: TProgressBar;
    Memo1: TMemo;
    Button3: TButton;
    ButtonRunTests: TButton;
    ListBoxAliases: TListBox;
    Timer2: TTimer;
    Button1: TButton;
    procedure FormCreate(Sender: TObject);
    procedure Timer1Timer(Sender: TObject);
    procedure ButtonSendClick(Sender: TObject);
    procedure Button3Click(Sender: TObject);
    procedure ButtonRunTestsClick(Sender: TObject);
    procedure Button1Click(Sender: TObject);
  private
    FThread: TClientThread;
    FMaxTraffic: Integer;
    FTraffic: Integer;
    FTrafficPercent: Integer;
    FTrafficCount: Integer;
    FErrorCount: Integer;
    procedure ThreadHandler(const S: string);
  end;

  TClientThread = class(TThread)
  private
    FClient: TTcpClient;
    FBuffer: string;
    FHandler: TGetStrProc;
  private
    procedure ClientError(Sender: TObject; SocketError: Integer);
    procedure ClientSend(Sender: TObject; Buf: PAnsiChar; var DataLen: Integer);
  public
    constructor Create(CreateSuspended: Boolean);
    procedure Update;
    procedure Send(const Msg: string);
    procedure Execute; override;
  public
    property Handler: TGetStrProc read FHandler write FHandler;
  end;

var
  FormMain: TFormMain;

implementation

{$R *.dfm}

{ TClientThread }

constructor TClientThread.Create(CreateSuspended: Boolean);
begin
  inherited;
  FreeOnTerminate := True;
end;

procedure TClientThread.Execute;
var
  Buf: Char;
const
  TERMINATOR: string = #13#10;
begin
  try
    FClient := TTcpClient.Create(nil);
    FClient.OnError := ClientError;
    FClient.OnSend := ClientSend;
    try
      FClient.RemoteHost := '192.168.1.22';
      FClient.RemotePort := '50000';
      if FClient.Connect = False then
      begin
        ShowMessage('Unable to connect to remote host.');
        Exit;
      end;
      FBuffer := '';
      while (Application.Terminated = False) and (Self.Terminated = False) and (FClient.Connected = True) do
      begin
        FClient.ReceiveBuf(Buf, 1);
        FBuffer := FBuffer + Buf;
        if Copy(FBuffer, Length(FBuffer) - Length(TERMINATOR) + 1, Length(TERMINATOR)) = TERMINATOR then
        begin
          Synchronize(Update);
          FBuffer := '';
        end;
      end;
    finally
      FClient.Free;
    end;
  except
    on E: Exception do
      ShowMessage('Exception' + ^M + E.ClassName + ^M + E.Message);
  end;
end;

procedure TClientThread.Send(const Msg: string);
begin
  if Assigned(FClient) then
    if FClient.Connected then
      FClient.Sendln(Msg);
end;

procedure TClientThread.Update;
begin
  if Assigned(FHandler) then
    FHandler(FBuffer);
end;

procedure TClientThread.ClientError(Sender: TObject; SocketError: Integer);
begin

end;

procedure TClientThread.ClientSend(Sender: TObject; Buf: PAnsiChar; var DataLen: Integer);
begin

end;

{ TFormMain }

procedure TFormMain.FormCreate(Sender: TObject);
begin
  FThread := TClientThread.Create(True);
  FThread.Handler := ThreadHandler;
  FThread.Resume;
  Timer1.Enabled := True;
end;

procedure TFormMain.ThreadHandler(const S: string);
var
  Msg: TSerialized;
begin
  Msg := TSerialized.Create;
  try
    {while Memo1.Lines.Count > 100 do
      Memo1.Lines.Delete(0);}
    Inc(FTraffic, Length(S));
    if Msg.Parse(S) then
    begin
      //Memo1.Lines.Add(Msg.ArrayData['buf'].StringData);
      StatusBar1.Panels[4].Text := Msg.ArrayData['buf'].StringData;
      if Msg['type'].StringData = 'handle' then
      begin
        // triggers in response to /READER_HANDLES command
        Memo1.Lines.Add(S);
      end;
      if Msg['type'].StringData = 'alias' then
      begin
        // triggers in response to /READER_EXEC_LIST command
        Memo1.Lines.Add(S);
      end;
      if Msg['type'].StringData = 'bucket' then
      begin
        // triggers in response to /READER_BUCKETS command
        Memo1.Lines.Add(S);
      end;
      if Msg['type'].StringData = 'exec' then
      begin
        // triggers when a process is started
        Memo1.Lines.Add(S);
      end;
    end
    else
    begin
      Memo1.Lines.Add(S);
      StatusBar1.Panels[4].Text := Msg.Serialized;
      FErrorCount := FErrorCount + 1;
      StatusBar1.Panels[3].Text := IntToStr(FErrorCount);
    end;
  finally
    Msg.Free;
  end;
end;

procedure TFormMain.Timer1Timer(Sender: TObject);
var
  F: Integer;
begin
  F := Round(1000 / Timer1.Interval);
  if FTraffic > FMaxTraffic then
    FMaxTraffic := FTraffic;
  StatusBar1.Panels[0].Text := Format('%.1f', [FTraffic / 1024]) + ' kb/s';
  StatusBar1.Panels[1].Text := Format('%.1f', [FMaxTraffic / 1024]) + ' kb/s max';
  if FMaxTraffic = 0 then
    FTrafficPercent := 0
  else
    FTrafficPercent := Round(FTraffic / FMaxTraffic * 100);
  StatusBar1.Panels[2].Text := IntToStr(FTrafficPercent) + '%';
  if FTrafficPercent < ProgressBar1.Position then
    ProgressBar1.Position := ProgressBar1.Position - 5
  else
    ProgressBar1.Position := FTrafficPercent;
  Inc(FTrafficCount);
  if FTrafficCount >= F then
    FTraffic := 0;
end;

procedure TFormMain.ButtonSendClick(Sender: TObject);
var
  msg: string;
begin
  if LabeledEditAliasesDest.Text <> '' then
    msg := ':exec INTERNAL ' + LabeledEditAliasesDest.Text + ' :' + LabeledEditAliasesTrailing.Text
  else
    msg := ':exec INTERNAL :' + LabeledEditAliasesTrailing.Text;
  FThread.Send(msg);
end;

procedure TFormMain.Button3Click(Sender: TObject);
begin
  FThread.Terminate;
end;

procedure TFormMain.ButtonRunTestsClick(Sender: TObject);
begin
  RunUnserializeTests;
end;

procedure TFormMain.Button1Click(Sender: TObject);
begin
  if Assigned(FThread) then
  begin
    FThread.Send('/READER_HANDLES');
    FThread.Send('/READER_EXEC_LIST');
    FThread.Send('/READER_BUCKETS');
  end;
end;

end.

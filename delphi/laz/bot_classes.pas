unit bot_classes;

{$mode delphi}

interface

uses
  Classes,
  process,
  SysUtils,
  lNet; // add '/usr/share/fpcsrc/2.6.4/utils/fppkg/lnet/' to include files paths under project options -> compiler options -> paths

type

  TSocketThread = class;
  TBotServer = class;
  TBotServerArray = class;

  { TBotMessage }

  TBotMessage = record
    Command: string;
    Data: string;
    Destination: string;
    Hostname: string;
    Nick: string;
    Params: string;
    Prefix: string;
    Server: string;
    TimeStamp: TDateTime;
    Trailing: string;
    User: string;
    Valid: Boolean;
  end;

  TBotReceiveEvent = procedure(const Server: TBotServer; const Msg: TBotMessage; const Data: string) of object;

  { TSocketThread }

  TSocketThread = class(Classes.TThread)
  private
    FBuffer: string;
    FServer: TBotServer;
    FSocket: lNet.TLTcp;
  private
    procedure SocketConnect(aSocket: TLSocket);
    procedure SocketDisconnect(aSocket: TLSocket);
    procedure SocketError(const msg: string; aSocket: TLSocket);
    procedure SocketReceive(aSocket: TLSocket);
  public
    constructor Create(CreateSuspended: Boolean);
  public
    procedure Update;
    procedure Send(const Msg: string);
    procedure Execute; override;
  public
    property Server: TBotServer read FServer write FServer;
  end;

  { TProcessThread }

  TProcessThread = class(Classes.TThread)
  private
    FOutputBuffer: string;
    FStderrBuffer: string;
  private
    FOnOutputChanged: TGetStrProc;
    FOnStderrChanged: TGetStrProc;
  public
    constructor Create(CreateSuspended: Boolean);
  public
    procedure OutputChanged;
    procedure StderrChanged;
    procedure Execute; override;
  public
    property OnOutputChanged: TGetStrProc read FOnOutputChanged write FOnOutputChanged;
    property OnStderrChanged: TGetStrProc read FOnStderrChanged write FOnStderrChanged;
  end;

  { TBotServer }

  TBotServer = class(TObject)
  private
    FRemoteHost: string;
    FRemotePort: Integer;
    FNickName: string;
    FUserName: string;
    FFullName: string;
    FHostName: string;
    FServerName: string;
    FNickServPasswordFileName: string;
    FHandler: TBotReceiveEvent;
    FThread: TSocketThread;
  public
    constructor Create(const Handler: TBotReceiveEvent);
    destructor Destroy; override;
  public
    procedure Connect(const RemoteHost, NickName, UserName, FullName, HostName, ServerName: string; const RemotePort: Integer);
    procedure Send(const Msg: string; const Obfuscate: Boolean = False);
  public
    property RemoteHost: string read FRemoteHost;
    property RemotePort: Integer read FRemotePort;
    property NickName: string read FNickName;
    property UserName: string read FUserName;
    property FullName: string read FFullName;
    property HostName: string read FHostName;
    property ServerName: string read FServerName;
    property NickServPasswordFileName: string read FNickServPasswordFileName;
  public
    property Handler: TBotReceiveEvent read FHandler write FHandler;
  end;

  { TBotServerArray }

  TBotServerArray = class(TObject)
  private
    FGlobalHandler: TBotReceiveEvent;
    FServers: Classes.TList;
  private
    function GetCount: Integer;
    function GetServer(const Index: Integer): TBotServer;
    function GetHostName(const HostName: string): TBotServer;
  public
    constructor Create(const GlobalHandler: TBotReceiveEvent);
    destructor Destroy; override;
  public
    function Add: TBotServer;
    function IndexOf(const HostName: string): Integer;
  public
    property Count: Integer read GetCount;
    property Servers[const Index: Integer]: TBotServer read GetServer;
    property HostNames[const HostName: string]: TBotServer read GetHostName; default;
  end;

implementation

uses
  bot_utils;

{ TSocketThread }

procedure TSocketThread.SocketConnect(aSocket: TLSocket);
begin
  FBuffer := '<< CONNECTED >>';
  Synchronize(Update);
  FBuffer := '';
end;

procedure TSocketThread.SocketDisconnect(aSocket: TLSocket);
begin
  FBuffer := '<< SOCKET DISCONNECTED >>';
  Synchronize(Update);
  FBuffer := '';
end;

procedure TSocketThread.SocketError(const msg: string; aSocket: TLSocket);
begin
  FBuffer := '<< SOCKET ERROR >>';
  Synchronize(Update);
  FBuffer := '';
end;

procedure TSocketThread.SocketReceive(aSocket: TLSocket);
var
  i: Integer;
  S: string;
  Lines: TStrings;
begin
  aSocket.GetMessage(S);
  Lines := TStringList.Create;
  try
    Lines.Text := FBuffer + S;
    for i := 0 to Lines.Count - 2 do
    begin
      FBuffer := Lines[i];
      Synchronize(Update);
    end;
    FBuffer := Lines[Lines.Count - 1];
    if Copy(S, Length(S) - 1, 2) = #13#10 then
    begin
      Synchronize(Update);
      FBuffer := '';
    end;
  finally
    Lines.Free;
  end;
end;

constructor TSocketThread.Create(CreateSuspended: Boolean);
begin
  FreeOnTerminate := True;
  inherited Create(CreateSuspended);
end;

procedure TSocketThread.Update;
var
  Msg: TBotMessage;
begin
  if Assigned(FServer.Handler) = False then
    Exit;
  Msg := ParseMessage(FBuffer);
  Msg.Server := FServer.RemoteHost;
  FServer.Handler(FServer, Msg, FBuffer);
end;

procedure TSocketThread.Send(const Msg: string);
begin
  if FSocket.Connected then
    FSocket.SendMessage(Msg + #13#10);
end;

procedure TSocketThread.Execute;
var
  i: Integer;
begin
  try
    FBuffer := '[ INITIALIZING SOCKET ]';
    Synchronize(Update);
    FBuffer := '';
    FSocket := lNet.TLTcp.Create(nil);
    FSocket.OnReceive := SocketReceive;
    FSocket.OnConnect := SocketConnect;
    FSocket.OnDisconnect := SocketDisconnect;
    FSocket.OnError := SocketError;
    try
      if FSocket.Connect(FServer.RemoteHost, FServer.RemotePort) = False then
      begin
        FBuffer := '[ CONNECTION ERROR ]';
        Synchronize(Update);
        FBuffer := '';
        Exit;
      end;
      i := 1000;
      repeat
        Dec(i);
        FSocket.CallAction;
        Sleep(50);
      until (i < 0) or (FSocket.Connected = True);
      if FSocket.Connected = False then
      begin
        FBuffer := '[ CONNECTION TIMEOUT ]';
        Synchronize(Update);
        FBuffer := '';
      end;
      Send('NICK ' + FServer.NickName);
      Send('USER ' + FServer.UserName + ' ' + FServer.HostName + ' ' + FServer.ServerName + ' :' + FServer.FullName);
      while (Self.Terminated = False) and (FSocket.Connected = True) do
      begin
        FSocket.CallAction;
        ThreadSwitch;
      end;
    finally
      Send('NickServ LOGOUT');
      Send('QUIT :dafuq');
      FSocket.CallAction;
      Sleep(500);
      FSocket.Free;
    end;
  except
    FBuffer := '[ EXCEPTION ERROR ]';
    Synchronize(Update);
  end;
end;

{ TProcessThread }

constructor TProcessThread.Create(CreateSuspended: Boolean);
begin
  FreeOnTerminate := True;
  inherited Create(CreateSuspended);
end;

procedure TProcessThread.OutputChanged;
begin
  if Assigned(FOnOutputChanged) = False then
    Exit;
  FOnOutputChanged(FOutputBuffer);
end;

procedure TProcessThread.StderrChanged;
begin
  if Assigned(FOnStderrChanged) = False then
    Exit;
  FOnStderrChanged(FStderrBuffer);
end;

procedure TProcessThread.Execute;
const
  BUF_SIZE = 2048;
var
  Proc: TProcess;
  bytes_read_out: Integer;
  bytes_read_err: Integer;
  Buffer: array[1..BUF_SIZE] of Byte;
  i: Integer;
begin
  Proc := TProcess.Create(nil);
  Proc.Executable := '/usr/bin/php';
  Proc.Parameters.Add('/home/jared/git/exec-irc-bot/delphi/laz/scripts/test.php');
  Proc.Options := [poUsePipes];
  Proc.Execute;
  repeat
    FOutputBuffer := '';
    FStderrBuffer := '';
    bytes_read_out := Proc.Output.Read(Buffer, BUF_SIZE);
    for i := 1 to bytes_read_out do
      FOutputBuffer := FOutputBuffer + Chr(Buffer[i]);
    Synchronize(OutputChanged);
    bytes_read_err := Proc.Stderr.Read(Buffer, BUF_SIZE);
    for i := 1 to bytes_read_err do
      FStderrBuffer := FStderrBuffer + Chr(Buffer[i]);
    Synchronize(StderrChanged);
  until (bytes_read_out = 0) and (bytes_read_err = 0);
  Proc.Free;
end;

{ TBotServer }

procedure TBotServer.Connect(const RemoteHost, NickName, UserName, FullName, HostName, ServerName: string; const RemotePort: Integer);
begin
  FRemoteHost := RemoteHost;
  FRemotePort := RemotePort;
  FNickName := NickName;
  FUserName := UserName;
  FFullName := FullName;
  FHostName := HostName;
  FServerName := ServerName;
  FThread.Start;
end;

constructor TBotServer.Create(const Handler: TBotReceiveEvent);
begin
  FHandler := Handler;
  FThread := TSocketThread.Create(True);
  FThread.Server := Self;
end;

destructor TBotServer.Destroy;
begin
  if Assigned(FThread) then
    FThread.Terminate;
  inherited Destroy;
end;

procedure TBotServer.Send(const Msg: string; const Obfuscate: Boolean = False);
begin
  FThread.Send(Msg);
  if Obfuscate = False then
    if Assigned(FHandler) then
      FHandler(Self, ParseMessage(Msg), Msg);
end;

{ TBotServerArray }

function TBotServerArray.Add: TBotServer;
begin
  Result := TBotServer.Create(FGlobalHandler);
  FServers.Add(Result);
end;

constructor TBotServerArray.Create(const GlobalHandler: TBotReceiveEvent);
begin
  FGlobalHandler := GlobalHandler;
  FServers := Classes.TList.Create;
end;

destructor TBotServerArray.Destroy;
var
  i: Integer;
begin
  for i := 0 to Count - 1 do
    Servers[i].Free;
  FServers.Free;
  inherited;
end;

function TBotServerArray.GetCount: Integer;
begin
  Result := FServers.Count;
end;

function TBotServerArray.GetHostName(const HostName: string): TBotServer;
var
  i: Integer;
begin
  i := IndexOf(HostName);
  if i >= 0 then
    Result := Servers[i]
  else
    Result := nil;
end;

function TBotServerArray.GetServer(const Index: Integer): TBotServer;
begin
  if (Index >= 0) and (Index < Count) then
    Result := TBotServer(FServers[Index])
  else
    Result := nil;
end;

function TBotServerArray.IndexOf(const HostName: string): Integer;
var
  i: Integer;
begin
  for i := 0 to Count - 1 do
    if Servers[i].HostName = HostName then
    begin
      Result := i;
      Exit;
    end;
  Result := -1;
end;

end.
